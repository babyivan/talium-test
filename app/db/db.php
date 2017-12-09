<?php

namespace app\db;

use app\config;

class db
{
    private $db;


    public function __construct()
    {
        $this->db = [];
        $this->create();
    }

    private function create()
    {
        foreach (config::get_db_rules() as $item => $value) {
            $sectors_range = explode('-', $item, 2);

            $sectors_range[0] = ((int)$sectors_range[0]);
            $sectors_range[1] = ((int)$sectors_range[1]);

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
                $this->db[$a] = new db_sector($rows_in_the_sector, $places_in_a_row);

            }
        }
    }

    public function get_all_sectors(): array
    {
        $array = [];
        foreach ($this->db as $key => $value) {
            //            $array[] = $key;
            $array[$key] = $this->db[$key]->get_status();
        }

        return $array;
    }

    public function get_sector_status_by_places(int $sector): array
    {
        return [
            'sector' => $sector,
            'data' => $this->db[$sector]->get_status(),
        ];
    }

    public function get_places_by_sector(int $sector)
    {
        return $this->db[$sector]->get_all_places();
    }

    public function get_place_by_sector(int $sector, int $place)
    {
        return $this->db[$sector]->get_place($place);
    }

    public function reserve_place_by_user(int $sector, int $place, string $user_name)
    {
        $this->db[$sector]->get_place($place)->status = db_place::STATUS_BOOKING;
        $this->db[$sector]->get_place($place)->owned = $user_name;
        $this->db[$sector]->place_reserve();
    }

    public function buy_place_by_user(int $sector, int $place, string $user_name)
    {
        $this->db[$sector]->get_place($place)->status = db_place::STATUS_OCCUPIED;
        $this->db[$sector]->get_place($place)->owned = $user_name;
        $this->db[$sector]->place_purchased();
    }

    //    public function user_click_on_place_by_sector($sector, $last_clicked_place, $place, $user_name) {
    //        $this->db[$sector]->getPlace($last_clicked_place)->removeSelectedByUser($user_name);
    //        $this->db[$sector]->getPlace($place)->setSelectedByUser($user_name);
    //    }

}