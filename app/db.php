<?php

namespace app;

class sector {
    private $places;
    
    private $all_places;
    private $free_places;
    private $the_process_of_booking;
    private $purchased_places;
    
    public function __construct(int $rows_in_the_sector, int $places_in_a_row) {
        $total = ($rows_in_the_sector * $places_in_a_row);
        
        //        $this->places = array_fill(1, $total, new place());
        for ($a = 1; $a < $total; $a++) {
            $this->places[$a] = new place();
        }
        
        $this->all_places = $this->free_places = $total;
        $this->the_process_of_booking = 0;
        $this->purchased_places = 0;
    }
    
    public function one_plus_to_booking() {
        $this->free_places--;
        $this->the_process_of_booking++;
    }
    
    public function one_plus_to_purchased() {
        $this->free_places--;
        $this->purchased_places++;
    }
    
    public function getAllPlaces(): array {
        return $this->places;
    }
    
    public function getPlace(int $place) {
        return $this->places[$place];
    }
    
    public function getStatusOfPlaces() {
        return [
            'all_places'             => $this->all_places,
            'free_places'            => $this->free_places,
            'the_process_of_booking' => $this->the_process_of_booking,
            'purchased_places'       => $this->purchased_places,
        ];
    }
}

class place {
    const STATUS_FREE = 0;
    const STATUS_BOOKING = 1;
    const STATUS_OCCUPIED = 2;
    
    public $status = self::STATUS_FREE;
    public $owned = null;
    
    //    public $selected_by_user;
    
    public function __construct() {
        //        $this->selected_by_user = [];
    }
    
    //    public function getSelectedByUsers(): array {
    //        return $this->selected_by_user;
    //    }
    //
    //    public function setSelectedByUser(string $selected_by_user) {
    //        //        $this->removeSelectedByUser($selected_by_user);
    //        $this->selected_by_user[] = $selected_by_user;
    //    }
    //
    //    public function removeSelectedByUser(string $user) {
    //        $i = array_search($user, $this->getSelectedByUsers());
    //        if ($i !== false)
    //            array_splice($this->selected_by_user, $i, 1);
    //    }
}

class db {
    private $db;
    
    
    public function __construct() {
        $this->db = [];
        $this->createDB();
    }
    
    private function createDB() {
        foreach (cfg::getBDConf() as $item => $value) {
            $sectors_range = explode('-', $item, 2);
            
            $sectors_range[0] = (int)$sectors_range[0];
            $sectors_range[1] = (int)$sectors_range[1];
            
            //Парсим согласно конфигу сектора, ряды, места
            for ($a = $sectors_range[0], $b = $sectors_range[1]; $a < $b; $a++) {
                //                list(, $rows_in_the_sector, $places_in_a_row,) = array_values($value);
                $rows_in_the_sector = $value['rows_in_the_sector'];
                $places_in_a_row = $value['places_in_a_row'];
                
                if (isset($value['custom'][$a])) {
                    if (array_key_exists('rows_in_the_sector', $value['custom'][$a]))
                        $rows_in_the_sector = $value['custom'][$a]['rows_in_the_sector'];
                    if (array_key_exists('places_in_a_row', $value['custom'][$a]))
                        $places_in_a_row = $value['custom'][$a]['places_in_a_row'];
                }
                $this->db[$a] = new sector($rows_in_the_sector, $places_in_a_row);
                
            }
        }
    }
    
    public function get_sectors(): array {
        $array = [];
        foreach ($this->db as $key => $value) {
            //            $array[] = $key;
            $array[$key] = $this->db[$key]->getStatusOfPlaces();
        }
        
        return $array;
    }
    
    public function get_sector_status_of_places($sector): array {
        return [
            'sector' => $sector,
            'data'   => $this->db[$sector]->getStatusOfPlaces(),
        ];
    }
    
    public function get_places_by_sector(int $sector) {
        return $this->db[$sector]->getAllPlaces();
    }
    
    public function get_place_by_sector(int $sector, int $place) {
        return $this->db[$sector]->getPlace($place);
    }
    
    public function reserve_place_by_user($sector, $place, $user_name) {
        $this->db[$sector]->getPlace($place)->status = place::STATUS_BOOKING;
        $this->db[$sector]->getPlace($place)->owned = $user_name;
        $this->db[$sector]->one_plus_to_booking();
    }
    
    public function buy_place_by_user($sector, $place, $user_name) {
        $this->db[$sector]->getPlace($place)->status = place::STATUS_OCCUPIED;
        $this->db[$sector]->getPlace($place)->owned = $user_name;
        $this->db[$sector]->one_plus_to_purchased();
    }
    
    //    public function user_click_on_place_by_sector($sector, $last_clicked_place, $place, $user_name) {
    //        $this->db[$sector]->getPlace($last_clicked_place)->removeSelectedByUser($user_name);
    //        $this->db[$sector]->getPlace($place)->setSelectedByUser($user_name);
    //    }
    
}