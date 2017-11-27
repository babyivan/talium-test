<?php

namespace app;


class ws_client {
    private $id;
    private $socket;
    private $handshake;
    private $isConnected;
    
    private $name;
    
    private $clicked_sector;
    private $clicked_place;
    
    public function __construct($id, $socket) {
        $this->id = $id;
        $this->socket = $socket;
        $this->handshake = false;
        $this->pid = null;
        $this->isConnected = true;
        
        $this->name = null;
    }
    
    
    public function getClickedSector() {
        return $this->clicked_sector;
    }
    
    public function setClickedSector($clicked_sector) {
        $this->clicked_sector = $clicked_sector;
    }
    
    public function getClickedPlace() {
        return $this->clicked_place;
    }
    
    public function setClickedPlace($clicked_place) {
        $this->clicked_place = $clicked_place;
    }
    
    public function getId() {
        return $this->id;
    }
    
    public function setId($id) {
        $this->id = $id;
    }
    
    public function getSocket() {
        return $this->socket;
    }
    
    public function setSocket($socket) {
        $this->socket = $socket;
    }
    
    public function getHandshake() {
        return $this->handshake;
    }
    
    public function setHandshake($handshake) {
        $this->handshake = $handshake;
    }
    
    public function getPid() {
        return $this->pid;
    }
    
    public function setPid($pid) {
        $this->pid = $pid;
    }
    
    public function isConnected() {
        return $this->isConnected;
    }
    
    public function setIsConnected($isConnected) {
        $this->isConnected = $isConnected;
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function setName($name) {
        $this->name = $name;
    }
}