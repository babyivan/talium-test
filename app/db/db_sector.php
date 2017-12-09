<?php

namespace app\db;

class db_sector
{
    private $places;

    private $all_places;
    private $free_places;
    private $reserved_places;
    private $purchased_places;

    public function __construct(int $rows_in_the_sector, int $places_in_a_row)
    {
        $total = ($rows_in_the_sector * $places_in_a_row);

        //        $this->places = array_fill(1, $total, new place());
        for ($a = 1; $a < $total; $a++) {
            $this->places[$a] = new db_place();
        }

        $this->all_places = $this->free_places = $total;
        $this->reserved_places = 0;
        $this->purchased_places = 0;
    }

    public function place_reserve()
    {
        $this->free_places--;
        $this->reserved_places++;
    }

    public function place_purchased()
    {
        $this->free_places--;
        $this->purchased_places++;
    }

    public function get_all_places(): array
    {
        return $this->places;
    }

    public function get_place(int $place)
    {
        return $this->places[$place];
    }

    public function get_status()
    {
        return [
            'all_places' => $this->all_places,
            'free_places' => $this->free_places,
            'reserved_places' => $this->reserved_places,
            'purchased_places' => $this->purchased_places,
        ];
    }
}