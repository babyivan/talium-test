<?php

namespace app;


class ws_client
{
    private $id;
    private $socket;
    private $handshake;

    private $name;

    private $last_selected_sector;
    private $last_selected_place;

    public function __construct(string $id, $socket)
    {
        $this->id = $id;
        $this->socket = $socket;
        $this->handshake = false;
        $this->pid = null;
        $this->isConnected = true;

        $this->name = null;
    }

    public function get_id(): string
    {
        return $this->id;
    }

    public function set_id(string $id)
    {
        $this->id = $id;
    }

    public function get_socket()
    {
        return $this->socket;
    }

    public function set_socket($socket)
    {
        $this->socket = $socket;
    }

    public function is_handshake(): bool
    {
        return $this->handshake;
    }

    public function set_handshake(bool $handshake)
    {
        $this->handshake = $handshake;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function set_name(string $name)
    {
        $this->name = $name;
    }

    public function get_last_selected_sector(): int
    {
        return $this->last_selected_sector;
    }

    public function set_last_selected_sector(int $last_selected_sector)
    {
        $this->last_selected_sector = $last_selected_sector;
    }

    public function get_last_selected_place(): int
    {
        return $this->last_selected_place;
    }

    public function set_last_selected_place(int $last_selected_place)
    {
        $this->last_selected_place = $last_selected_place;
    }


}