<?php

namespace app\db;

class db_place
{
    const STATUS_FREE = 0;
    const STATUS_BOOKING = 1;
    const STATUS_OCCUPIED = 2;

    public $status = self::STATUS_FREE;
    public $owned = null;

    //    public $selected_by_user;

    public function __construct()
    {
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