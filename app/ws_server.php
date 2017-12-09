<?php

namespace app;

use app\db\db;

class ws_server
{

    protected $master;

    protected $connected_sockets;
    protected $null = null;

    /** @var \app\ws_client $connected_clients */
    private $connected_clients;
    private $all_clients;

    private $currently_connected = 0;
    private $currently_connected_last_value = 0;

    private $db;


    public function __construct()
    {
        $this->create_socket();
        $this->db = new db();
        $this->log("DB was init !", config::logtag_db());
        $this->run();
    }


    private final function create_socket()
    {
        if (($this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) < 0) {
            die("socket_create() failed, reason: " . $this->socket_error());
        }

        $this->log("Master Socket [{$this->master}] created!", config::logtag_sys());

        socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1);

        if (($ret = socket_bind($this->master, config::get_host(), config::get_port())) === false) {
            die("socket_bind() failed, reason: " . $this->socket_error());
        }

        $this->log('Socket bound to [' . config::get_host() . ':' . config::get_port() . ']', config::logtag_sys());

        if (($ret = socket_listen($this->master, config::get_max_allowed_connections())) === false) {
            die("socket_listen() failed, reason: " . $this->socket_error());
        }

        $this->connected_sockets[] = $this->master;

        $this->log('Start listening on [' . config::get_host() . ':' . config::get_port() . '] socket ...', config::logtag_sys());
    }

    public final function socket_error(): string
    {
        return socket_strerror(socket_last_error());
    }

    public final function log(string $msg, string $tag)
    {
        $msg = explode('\n', $msg);
        foreach ($msg as $line) {
            echo date('Y-m-d H:i:s') . ' [' . $tag . '] ' . $line . PHP_EOL;
        }
    }

    public function run()
    {
        $this->log("...", config::logtag_sys());
        while (true) {
            $changed_sockets = $this->connected_sockets;

            if (socket_select($changed_sockets, $this->null, $this->null, 0, 1000) !== false) {

                foreach ($changed_sockets as $socket) {
                    # master socket changed means there is a new socket request
                    if ($socket == $this->master) {
                        # Если не можем принять сокет
                        if (($socket_accept = socket_accept($this->master)) === false) {
                            self::log('socket_accept() failed: reason: ' . $this->socket_error(), config::logtag_sys());
                            continue;
                        } # Подключаем сокет
                        else {
                            $this->connect($socket_accept);
                        }
                    } # client socket has sent data
                    else {
                        $this->log("Finding the socket that associated to the client...", config::logtag_sys());
                        $client = $this->get_client_by_socket($socket);
                        if ($client) {
                            $this->log("Receiving data from the client [#{$client->get_id()}]", config::logtag_sys());
                            $client_data = null;
                            while ($bytes = socket_recv($socket, $r_data, 2048, MSG_DONTWAIT)) {
                                $client_data .= $r_data;
                            }
                            //                            $bytes = @socket_recv($socket, $client_data, 2048, MSG_DONTWAIT);
                            if (!$client->is_handshake()) {
                                $this->log("Doing the handshake by client [#{$client->get_id()}]", config::logtag_sys());
                                if (!$this->make_handshake($client, $client_data))
                                    $this->disconnect($client);
                                else
                                    $this->log("Wait data from client [#{$client->get_id()}] ...", config::logtag_sys());
                                //                                                                    $this->startProcess($client);
                            } else if ($bytes === 0) {
                                $this->log("Bytes from client [#{$client->get_id()}] -> {$bytes} ?!", config::logtag_sys());
                                $this->disconnect($client);
                                continue;
                            } else {
                                // When received data from client
                                $this->action($client, $client_data);
                            }
                        }
                    }
                }
            }
            if ($this->currently_connected !== $this->currently_connected_last_value) {
                $this->currently_connected_last_value = $this->currently_connected;
                $this->log((PHP_EOL . PHP_EOL . "Connected clients: {$this->currently_connected}" . PHP_EOL), config::logtag_info());
            }
        }
    }

    private function connect($socket)
    {
        $client = new ws_client(uniqid(), $socket);
        $this->connected_clients[] = $client;
        $this->connected_sockets[] = $socket;

        $this->currently_connected++;
        $this->log("Client [#{$client->get_id()}] is successfully created on socked = [{$socket}] =)", config::logtag_sys());
    }

    private function get_client_by_socket($socket)
    {
        /** @var \app\ws_client $client */
        foreach ($this->connected_clients as $client) {
            if ($client->get_socket() == $socket) {
                $this->log("Client [#{$client->get_id()}] found by socket [{$socket}]", config::logtag_sys());

                return $client;
            }
        }

        return false;
    }

    private function make_handshake(ws_client $client, string $headers)
    {
        $this->log("Getting client [#{$client->get_id()}] WebSocket version...", config::logtag_sys());
        if (preg_match("/Sec-WebSocket-Version: (.*)\r\n/", $headers, $match)) {
            $version = $match[1];
        } else {
            $this->log("The client [#{$client->get_id()}] doesn't support WebSocket", config::logtag_sys());

            return false;
        }

        if ($version == 13) {
            if (preg_match("/GET (.*) HTTP/", $headers, $match))
                $root = $match[1];
            if (preg_match("/Host: (.*)\r\n/", $headers, $match))
                $host = $match[1];
            if (preg_match("/Origin: (.*)\r\n/", $headers, $match))
                $origin = $match[1];
            if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $headers, $match))
                $key = $match[1];


            $this->log("Generating Sec-WebSocket-Accept key for client [#{$client->get_id()}] ...", config::logtag_sys());

            $acceptKey = base64_encode(sha1(($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11'), true));

            $upgrade = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: {$acceptKey}\r\n\r\n";


            socket_write($client->get_socket(), $upgrade, strlen($upgrade));

            $client->set_handshake(true);

            $this->log("Handshake is successfully done for client [#{$client->get_id()}] !", config::logtag_sys());

            return true;
        } else {
            $this->log("WebSocket version 13 required (the client supports version {$version}).", config::logtag_sys());

            return false;
        }

    }

    private function disconnect(ws_client $client)
    {
        $this->log("Disconnecting client [#{$client->get_id()}] ...", config::logtag_sys());

        $i = array_search($client, $this->connected_clients);
        $j = array_search($client->get_socket(), $this->connected_sockets);

        if ($j >= 0) {
            if ($client->get_socket()) {
                array_splice($this->connected_sockets, $j, 1);
                socket_shutdown($client->get_socket(), 2);
                socket_close($client->get_socket());
                $this->log("Socket closed !", config::logtag_sys());
            }
        }
        if ($i >= 0) {
            array_splice($this->connected_clients, $i, 1);
        }

        $this->currently_connected--;
        $this->log("Client [#{$client->get_id()}] disconnected.", config::logtag_sys());
    }

    private function action(ws_client $client, string $data)
    {
        $action = $this->unmask($data);

        if ($action == chr(3) . chr(233)) {
            $this->disconnect($client);

            return;
        }
        //        $json_decode = rawurldecode($action);
        $this->log("Performing action: {$action} from client [#{$client->get_id()}]", config::logtag_info());
        $json_decode = json_decode($action, true);

        if ($json_decode === null) {
            $this->disconnect($client);

            return;
        }

        $u_action = $json_decode['action'] ?? "default";
        $u_action_data = $json_decode['action_data'] ?? null;
        unset($action);

        switch ($u_action) {

            case "register":
                if ($u_action_data === null) {
                    $this->send_to_client($client, $this->response_to_string('register', [
                        'status' => false,
                        'msg' => "invalid data",
                    ]));

                    return;
                }

                if (strpos($u_action_data, ' ') !== false) {
                    $this->send_to_client($client, $this->response_to_string('register', [
                        'status' => false,
                        'msg' => "*space* not allowed",
                    ]));

                    return;
                }


                if ($this->check_if_client_exits($u_action_data) === false) {
                    $this->send_to_client($client, $this->response_to_string('register', [
                        'status' => false,
                        'msg' => "user ID already taken: {$client->get_name()}",
                    ]));

                    return;
                }

                $client->set_name($u_action_data);

                $this->all_clients[] = $this->register_client($client);

                $this->send_to_client($client, $this->response_to_string('register', [
                    'status' => true,
                    'user_name' => $u_action_data,
                ]));

                $this->send_to_all($this->response_to_string('msg', "User register: {$client->get_name()}"));
                break;


            case "login":
                $action_data = $json_decode['action_data'];

                if (($cl = $this->get_client_by_login($action_data)) !== false) {
                    $client->set_name($cl->get_name());

                    $this->send_to_client($client, $this->response_to_string('login', [
                        'status' => true,
                        'msg' => "Welcome back: {$client->get_name()}",
                        'user_name' => $client->get_name(),
                    ]));
                } else
                    $this->send_to_client($client, $this->response_to_string('login', [
                        'status' => true,
                        'msg' => "Client by ID: {$json_decode['login']} not found.",
                    ]));
                break;


            case "sectors_get":
                if ($client->get_name() !== $this->null)
                    $this->send_to_client($client, $this->response_to_string('sectors', $this->db->get_all_sectors()));
                break;


            case "selected_sector":
                if ($client->get_name() !== $this->null) {

                    $client->set_last_selected_sector($u_action_data);

                    $this->send_to_client($client, $this->response_to_string('places', $this->db->get_places_by_sector($client->get_last_selected_sector())));
                    $this->send_to_all($this->response_to_string('msg', "User #{$client->get_name()} enter in sector #{$client->get_last_selected_sector()}"));
                }
                break;


            case "selected_place":

                if ($client->get_name() !== $this->null) {

                    $client->set_last_selected_place($u_action_data);

                    $this->send_to_client($client, $this->response_to_string('place_info', $this->db->get_place_by_sector($client->get_last_selected_sector(), $client->get_last_selected_place())));
                    $this->send_to_all($this->response_to_string('msg', "User #{$client->get_name()} click on place #{$client->get_last_selected_place()} in sector  #{$client->get_last_selected_place()}"));
                }
                break;


            case "place_reserve":
                if ($client->get_name() !== $this->null) {

                    $this->db->reserve_place_by_user($client->get_last_selected_sector(), $client->get_last_selected_place(), $client->get_name());

                    $this->send_to_all_on_current_sector($client->get_last_selected_sector(), $this->response_to_string('places', $this->db->get_places_by_sector($client->get_last_selected_sector())));
                    $this->send_to_all_on_current_place($client->get_last_selected_sector(), $client->get_last_selected_place(), $this->response_to_string('place_info', $this->db->get_place_by_sector($client->get_last_selected_sector(), $client->get_last_selected_place())));
                    $this->send_to_all($this->response_to_string('msg', "User #{$client->get_name()} reserve place #{$client->get_last_selected_place()} in sector #{$client->get_last_selected_sector()}"));

                    $this->send_to_all($this->response_to_string('sector_update', $this->db->get_sector_status_by_places($client->get_last_selected_sector())));
                }
                break;


            case "place_buy":
                if ($client->get_name() !== $this->null) {

                    $this->db->buy_place_by_user($client->get_last_selected_sector(), $client->get_last_selected_place(), $client->get_name());

                    $this->send_to_all_on_current_sector($client->get_last_selected_sector(), $this->response_to_string('places', $this->db->get_places_by_sector($client->get_last_selected_sector())));
                    $this->send_to_all_on_current_place($client->get_last_selected_sector(), $client->get_last_selected_place(), $this->response_to_string('place_info', $this->db->get_place_by_sector($client->get_last_selected_sector(), $client->get_last_selected_place())));
                    $this->send_to_all($this->response_to_string('msg', "User #{$client->get_name()} buy place #{$client->get_last_selected_place()} in sector #{$client->get_last_selected_sector()}"));

                    $this->send_to_all($this->response_to_string('sector_update', $this->db->get_sector_status_by_places($client->get_last_selected_sector())));
                }
                break;


            default:
//                echo '777';
                break;
        }

    }

    private function unmask(string $payload)
    {
        //        $length = ord($payload[1]) & 127;
        //
        //        if ($length == 126) {
        //            $masks = substr($payload, 4, 4);
        //            $data = substr($payload, 8);
        //            $len = (ord($payload[2]) << 8) + ord($payload[3]);
        //        }
        //        elseif ($length == 127) {
        //            $masks = substr($payload, 10, 4);
        //            $data = substr($payload, 14);
        //            $len = (ord($payload[2]) << 56) + (ord($payload[3]) << 48) + (ord($payload[4]) << 40) + (ord($payload[5]) << 32) + (ord($payload[6]) << 24) + (ord($payload[7]) << 16) + (ord($payload[8]) << 8) + ord($payload[9]);
        //        }
        //        else {
        //            $masks = substr($payload, 2, 4);
        //            $data = substr($payload, 6);
        //            $len = $length;
        //        }
        //
        //        $text = '';
        //        for ($i = 0; $i < $len; ++$i) {
        //            $text .= $data[$i] ^ $masks[$i % 4];
        //        }
        //
        //        return $text;
        $M = array_map("ord", str_split($payload));
        $L = $M[1] AND 127;

        if ($L == 126)
            $iFM = 4;
        else if ($L == 127)
            $iFM = 10;
        else
            $iFM = 2;

        $Masks = array_slice($M, $iFM, 4);

        $Out = "";
        for ($i = $iFM + 4, $j = 0; $i < count($M); $i++, $j++) {
            $Out .= chr($M[$i] ^ $Masks[$j % 4]);
        }

        return $Out;
    }

    function send_to_client(ws_client $client, string $text)
    {
        $this->log("Send '" . $text . "' to client #{$client->get_id()}", config::logtag_info());
        //        $text = rawurlencode($text);
        $text = $this->encode($text);
        if (socket_write($client->get_socket(), $text, strlen($text)) === false) {
            $this->log("Unable to write to client #{$client->get_id()}'s socket", config::logtag_sys());
            $this->disconnect($client);
        }
    }

    private function encode(string $text)
    {
        //        // 0x1 text frame (FIN + opcode)
        //        $b1 = 0x80 | (0x1 & 0x0f);
        //        $length = strlen($text);
        //
        //        if ($length <= 125)
        //            $header = pack('CC', $b1, $length);
        //        elseif ($length > 125 && $length < 65536)
        //            $header = pack('CCS', $b1, 126, $length);
        //        elseif ($length >= 65536)
        //            $header = pack('CCN', $b1, 127, $length);
        //
        //        return $header . $text;

        // inspiration for Encode() method : http://stackoverflow.com/questions/8125507/how-can-i-send-and-receive-websocket-messages-on-the-server-side
        $L = strlen($text);
        $bHead = [];
        $bHead[0] = 129; // 0x1 text frame (FIN + opcode)
        if ($L <= 125) {
            $bHead[1] = $L;
        } else if ($L >= 126 && $L <= 65535) {
            $bHead[1] = 126;
            $bHead[2] = ($L >> 8) & 255;
            $bHead[3] = ($L) & 255;
        } else {
            $bHead[1] = 127;
            $bHead[2] = ($L >> 56) & 255;
            $bHead[3] = ($L >> 48) & 255;
            $bHead[4] = ($L >> 40) & 255;
            $bHead[5] = ($L >> 32) & 255;
            $bHead[6] = ($L >> 24) & 255;
            $bHead[7] = ($L >> 16) & 255;
            $bHead[8] = ($L >> 8) & 255;
            $bHead[9] = ($L) & 255;
        }

        return (implode(array_map("chr", $bHead)) . $text);
    }

    private function response_to_string(string $status = 'msg', $msg): string
    {
        return json_encode([
            'type' => $status,
            'data' => $msg,
        ]);
    }

    private function check_if_client_exits(string $client_name): bool
    {
        $i = true;
        if (is_array($this->all_clients))
            foreach ($this->all_clients as $item => $val) {
                if ($client_name == $val->get_name()) {
                    $i = false;
                    break;
                }

            }

        return $i;
    }

    private function register_client($client)
    {
        return $client;
    }

    function send_to_all(string $text)
    {
        $this->log("Send to all", config::logtag_info());

        foreach ($this->connected_clients as $client) {
            $this->send_to_client($client, $text);
        }
    }

    private function get_client_by_login(string $login)
    {
        /** @var \app\ws_client $client */
        foreach ($this->all_clients as $client) {
            if ($client->get_name() === $login) {
                $this->log("Client found by login [{$login}]", config::logtag_sys());

                return $client;
            }
        }

        return false;
    }

    function send_to_all_on_current_sector(int $sector, string $text)
    {
        $this->log("Send to all in sector #{$sector}", config::logtag_info());

        /** @var \app\ws_client $client */
        foreach ($this->connected_clients as $client) {
            if ($client->get_last_selected_sector() === $sector)
                $this->send_to_client($client, $text);
        }
    }

    function send_to_all_on_current_place(int $sector, int $place, string $text)
    {
        $this->log("Send to all in sector #{$sector} on place #{$place}", config::logtag_info());

        /** @var \app\ws_client $client */
        foreach ($this->connected_clients as $client) {
            if ($client->get_last_selected_sector() === $sector)
                if ($client->get_last_selected_place() === $place)
                    $this->send_to_client($client, $text);
        }
    }

//    private function startProcess(ws_client $client)
//    {
//        $this->log("Start a client process", config::logtag_sys());
//        $pid = pcntl_fork();
//        if ($pid) {
//            pcntl_wait($status);
//            //            die('could not fork');
//        } else if ($pid) { // process
//            $client->set_pid($pid);
//        } else {
//            // we are the child
//            while (true) {
//                // check if the client is connected
//                if (!$client->is_connected()) {
//                    break;
//                }
//                //                 push something to the client
//                //                $seconds = rand(2, 5);
//                //                $msg = "I am waiting {$seconds} seconds";
//                //                $this->send_to_client($client, $msg);
//                sleep(1);
//            }
//        }
//    }

}