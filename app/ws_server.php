<?php

namespace app;

class ws_server {
    
    protected $master;
    
    protected $connected_sockets;
    protected $null = null;
    
    /** @var \app\ws_client $connected_clients */
    private $connected_clients;
    private $all_clients;
    
    private $currently_connected = 0;
    private $currently_connected_last_data = 0;
    
    private $db;
    
    
    public function __construct() {
        $this->createSocket();
        $this->db = new db();
        $this->log("DB was init !", cfg::getTAG_DB());
        $this->run();
    }
    
    
    private final function createSocket() {
        if (($this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) < 0) {
            die("socket_create() failed, reason: " . $this->socket_error());
        }
        
        $this->log("Master Socket [{$this->master}] created!", cfg::getTAG_SYS());
        
        socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1);
        
        if (($ret = socket_bind($this->master, cfg::getHost(), cfg::getPort())) === false) {
            die("socket_bind() failed, reason: " . $this->socket_error());
        }
        
        $this->log('Socket bound to [' . cfg::getHost() . ':' . cfg::getPort() . ']', cfg::getTAG_SYS());
        
        if (($ret = socket_listen($this->master, cfg::getMaxConnection())) === false) {
            die("socket_listen() failed, reason: " . $this->socket_error());
        }
        
        $this->connected_sockets[] = $this->master;
        
        $this->log('Start listening on [' . cfg::getHost() . ':' . cfg::getPort() . '] socket ...', cfg::getTAG_SYS());
    }
    
    public function log(string $msg, string $tag) {
        $msg = explode('\n', $msg);
        foreach ($msg as $line) {
            echo date('Y-m-d H:i:s') . ' [' . $tag . '] ' . $line . PHP_EOL;
        }
    }
    
    final function socket_error(): string {
        return socket_strerror(socket_last_error());
    }
    
    public function run() {
        $this->log("...", cfg::getTAG_SYS());
        while (true) {
            $changed_sockets = $this->connected_sockets;
            
            if (socket_select($changed_sockets, $this->null, $this->null, 0, 1000) !== false) {
                
                foreach ($changed_sockets as $socket) {
                    # master socket changed means there is a new socket request
                    if ($socket == $this->master) {
                        # Если не можем принять сокет
                        if (($socket_accept = socket_accept($this->master)) === false) {
                            self::log('socket_accept() failed: reason: ' . $this->socket_error(), cfg::getTAG_SYS());
                            continue;
                        }
                        # Подключаем сокет
                        else {
                            $this->connect($socket_accept);
                        }
                    }
                    # client socket has sent data
                    else {
                        $this->log("Finding the socket that associated to the client...", cfg::getTAG_SYS());
                        $client = $this->getClientBySocket($socket);
                        if ($client) {
                            $this->log("Receiving data from the client [#{$client->getId()}]", cfg::getTAG_SYS());
                            $client_data = null;
                            while ($bytes = socket_recv($socket, $r_data, 2048, MSG_DONTWAIT)) {
                                $client_data .= $r_data;
                            }
                            //                            $bytes = @socket_recv($socket, $client_data, 2048, MSG_DONTWAIT);
                            if (!$client->getHandshake()) {
                                $this->log("Doing the handshake by client [#{$client->getId()}]", cfg::getTAG_SYS());
                                if (!$this->handshake($client, $client_data))
                                    $this->disconnect($client);
                                else
                                    $this->log("Wait data from client [#{$client->getId()}] ...", cfg::getTAG_SYS());
                                //                                                                    $this->startProcess($client);
                            }
                            elseif ($bytes === 0) {
                                $this->log("Bytes from client [#{$client->getId()}] -> {$bytes} ?!", cfg::getTAG_SYS());
                                $this->disconnect($client);
                                continue;
                            }
                            else {
                                // When received data from client
                                $this->action($client, $client_data);
                            }
                        }
                    }
                }
            }
            if ($this->currently_connected !== $this->currently_connected_last_data) {
                $this->currently_connected_last_data = $this->currently_connected;
                $this->log((PHP_EOL . PHP_EOL . "Connected clients: {$this->currently_connected}" . PHP_EOL), cfg::getTAG_INFO());
            }
        }
    }
    
    function send_to_client(ws_client $client, string $text) {
        $this->log("Send '" . $text . "' to client #{$client->getId()}", cfg::getTAG_INFO());
        //        $text = rawurlencode($text);
        $text = $this->encode($text);
        if (socket_write($client->getSocket(), $text, strlen($text)) === false) {
            $this->log("Unable to write to client #{$client->getId()}'s socket", cfg::getTAG_SYS());
            $this->disconnect($client);
        }
    }
    
    function send_to_all(string $text) {
        $this->log("Send to all", cfg::getTAG_INFO());
        
        foreach ($this->connected_clients as $client) {
            $this->send_to_client($client, $text);
        }
    }
    
    function send_to_all_on_current_sector(int $sector, string $text) {
        $this->log("Send to all in sector #{$sector}", cfg::getTAG_INFO());
        
        /** @var \app\ws_client $client */
        foreach ($this->connected_clients as $client) {
            if ($client->getClickedSector() === $sector)
                $this->send_to_client($client, $text);
        }
    }
    
    function send_to_all_on_current_place(int $sector, int $place, string $text) {
        $this->log("Send to all in sector #{$sector} on place #{$place}", cfg::getTAG_INFO());
        
        /** @var \app\ws_client $client */
        foreach ($this->connected_clients as $client) {
            if ($client->getClickedSector() === $sector)
                if ($client->getClickedPlace() === $place)
                    $this->send_to_client($client, $text);
        }
    }
    
    private function action(ws_client $client, string $data) {
        $action = $this->unmask($data);
        
        if ($action == chr(3) . chr(233)) {
            $this->disconnect($client);
            
            return;
        }
        //        $json_decode = rawurldecode($action);
        $this->log("Performing action: {$action} from client [#{$client->getId()}]", cfg::getTAG_INFO());
        $json_decode = json_decode($action, true);
        
        if ($json_decode !== null) {
            if (array_key_exists('register', $json_decode)) {
                if (!!$this->check_if_client_exits($json_decode['register'])) {
                    $client->setName($json_decode['register']);
                    
                    $this->all_clients[] = $this->register_client($client);
                    
                    $this->send_to_client($client, $this->response_array('register', [
                        'status'    => true,
                        'user_name' => $json_decode['register'],
                    ]));
                    
                    $this->send_to_all($this->response_array('msg', "User register: {$client->getName()}"));
                }
                else {
                    $this->send_to_client($client, $this->response_array('register', [
                        'status' => false,
                        'msg'    => "user ID already taken: {$client->getName()}",
                    ]));
                }
            }
            elseif (array_key_exists('login', $json_decode)) {
                if (($cl = $this->getClientByLogin($json_decode['login'])) !== false) {
                    $client->setName($cl->getName());
                    
                    $this->send_to_client($client, $this->response_array('login', [
                        'status'    => true,
                        'msg'       => "Welcome back: {$client->getName()}",
                        'user_name' => $client->getName(),
                    ]));
                }
                else
                    $this->send_to_all($this->response_array('login', [
                        'status' => true,
                        'msg'    => "Client by ID: {$json_decode['login']} not found.",
                    ]));
            }
            elseif (array_key_exists('sectors_get', $json_decode)) {
                if ($client->getName() !== $this->null) {
                    $this->send_to_client($client, $this->response_array('sectors', $this->db->get_sectors()));
                }
            }
            elseif (array_key_exists('selected_sector', $json_decode)) {
                if ($client->getName() !== $this->null) {
                    
                    $client->setClickedSector($json_decode['selected_sector']);
                    
                    $this->send_to_client($client, $this->response_array('places', $this->db->get_places_by_sector($client->getClickedSector())));
                    $this->send_to_all($this->response_array('msg', "User #{$client->getName()} enter in sector #{$client->getClickedSector()}"));
                }
            }
            elseif (array_key_exists('selected_place', $json_decode)) {
                if ($client->getName() !== $this->null) {
                    
                    //                    $this->db->user_click_on_place_by_sector($client->getClickedSector(), $client->getClickedPlace(), $json_decode['selected_place'], $client->getName());
                    
                    $client->setClickedPlace($json_decode['selected_place']);
                    
                    //                    $this->send_to_all($this->response_array('places', $this->db->get_places_by_sector($client->getClickedSector())));
                    $this->send_to_client($client, $this->response_array('place_info', $this->db->get_place_by_sector($client->getClickedSector(), $client->getClickedPlace())));
                    $this->send_to_all($this->response_array('msg', "User #{$client->getName()} click on place #{$client->getClickedPlace()} in sector  #{$client->getClickedSector()}"));
                }
            }
            elseif (array_key_exists('place_reserve', $json_decode)) {
                if ($client->getName() !== $this->null) {
                    
                    $this->db->reserve_place_by_user($client->getClickedSector(), $client->getClickedPlace(), $client->getName());
                    
                    $this->send_to_all_on_current_sector($client->getClickedSector(), $this->response_array('places', $this->db->get_places_by_sector($client->getClickedSector())));
                    $this->send_to_all_on_current_place($client->getClickedSector(), $client->getClickedPlace(), $this->response_array('place_info', $this->db->get_place_by_sector($client->getClickedSector(), $client->getClickedPlace())));
                    $this->send_to_all($this->response_array('msg', "User #{$client->getName()} reserve place #{$client->getClickedPlace()} in sector #{$client->getClickedSector()}"));
                    
                    $this->send_to_all($this->response_array('sector_update', $this->db->get_sector_status_of_places($client->getClickedSector())));
                }
            }
            elseif (array_key_exists('place_buy', $json_decode)) {
                if ($client->getName() !== $this->null) {
                    
                    $this->db->buy_place_by_user($client->getClickedSector(), $client->getClickedPlace(), $client->getName());
                    
                    $this->send_to_all_on_current_sector($client->getClickedSector(), $this->response_array('places', $this->db->get_places_by_sector($client->getClickedSector())));
                    $this->send_to_all_on_current_place($client->getClickedSector(), $client->getClickedPlace(), $this->response_array('place_info', $this->db->get_place_by_sector($client->getClickedSector(), $client->getClickedPlace())));
                    $this->send_to_all($this->response_array('msg', "User #{$client->getName()} buy place #{$client->getClickedPlace()} in sector #{$client->getClickedSector()}"));
                    
                    $this->send_to_all($this->response_array('sector_update', $this->db->get_sector_status_of_places($client->getClickedSector())));
                }
            }
        }
    }
    
    private function check_if_client_exits($client_name) {
        $i = true;
        if (is_array($this->all_clients))
            foreach ($this->all_clients as $item => $val) {
                if ($client_name == $val->getName()) {
                    $i = false;
                    break;
                }
                
            }
        
        return $i;
    }
    
    private function register_client($client) {
        return $client;
    }
    
    private function response_array(string $status = 'msg', $msg): string {
        return json_encode([
            'type' => $status,
            'data' => $msg,
        ]);
    }
    
    private function connect($socket) {
        $client = new ws_client(uniqid(), $socket);
        $this->connected_clients[] = $client;
        $this->connected_sockets[] = $socket;
        
        $this->currently_connected++;
        $this->log("Client [#{$client->getId()}] is successfully created on socked = [{$socket}] =)", cfg::getTAG_SYS());
    }
    
    private function disconnect(ws_client $client) {
        $this->log("Disconnecting client [#{$client->getId()}] ...", cfg::getTAG_SYS());
        $client->setIsConnected(false);
        
        $i = array_search($client, $this->connected_clients);
        $j = array_search($client->getSocket(), $this->connected_sockets);
        
        if ($j >= 0) {
            if ($client->getSocket()) {
                array_splice($this->connected_sockets, $j, 1);
                socket_shutdown($client->getSocket(), 2);
                socket_close($client->getSocket());
                $this->log("Socket closed !", cfg::getTAG_SYS());
            }
        }
        if ($i >= 0) {
            array_splice($this->connected_clients, $i, 1);
        }
        
        $this->currently_connected--;
        $this->log("Client [#{$client->getId()}] disconnected.", cfg::getTAG_SYS());
    }
    
    private function getClientBySocket($socket) {
        /** @var \app\ws_client $client */
        foreach ($this->connected_clients as $client) {
            if ($client->getSocket() == $socket) {
                $this->log("Client [#{$client->getId()}] found by socket [{$socket}]", cfg::getTAG_SYS());
                
                return $client;
            }
        }
        
        return false;
    }
    
    private function getClientByLogin(string $login) {
        /** @var \app\ws_client $client */
        foreach ($this->all_clients as $client) {
            if ($client->getName() === $login) {
                $this->log("Client found by login [{$login}]", cfg::getTAG_SYS());
                
                return $client;
            }
        }
        
        return false;
    }
    
    private function startProcess(ws_client $client) {
        $this->log("Start a client process", cfg::getTAG_SYS());
        $pid = pcntl_fork();
        if ($pid) {
            pcntl_wait($status);
            //            die('could not fork');
        }
        elseif ($pid) { // process
            $client->setPid($pid);
        }
        else {
            // we are the child
            while (true) {
                // check if the client is connected
                if (!$client->isConnected()) {
                    break;
                }
                //                 push something to the client
                //                $seconds = rand(2, 5);
                //                $msg = "I am waiting {$seconds} seconds";
                //                $this->send_to_client($client, $msg);
                sleep(1);
            }
        }
    }
    
    private function handshake(ws_client $client, string $headers) {
        
        $this->log("Getting client [#{$client->getId()}] WebSocket version...", cfg::getTAG_SYS());
        if (preg_match("/Sec-WebSocket-Version: (.*)\r\n/", $headers, $match)) {
            $version = $match[1];
        }
        else {
            $this->log("The client [#{$client->getId()}] doesn't support WebSocket", cfg::getTAG_SYS());
            
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
            
            
            $this->log("Generating Sec-WebSocket-Accept key for client [#{$client->getId()}] ...", cfg::getTAG_SYS());
            
            $acceptKey = base64_encode(sha1(($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11'), true));
            
            $upgrade = "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: {$acceptKey}\r\n\r\n";
            
            
            socket_write($client->getSocket(), $upgrade, strlen($upgrade));
            
            $client->setHandshake(true);
            
            $this->log("Handshake is successfully done for client [#{$client->getId()}] !", cfg::getTAG_SYS());
            
            return true;
        }
        else {
            $this->log("WebSocket version 13 required (the client supports version {$version}).", cfg::getTAG_SYS());
            
            return false;
        }
        
    }
    
    
    private function unmask(string $payload) {
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
    
    private function encode(string $text) {
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
        }
        else if ($L >= 126 && $L <= 65535) {
            $bHead[1] = 126;
            $bHead[2] = ($L >> 8) & 255;
            $bHead[3] = ($L) & 255;
        }
        else {
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
    
}