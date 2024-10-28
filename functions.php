<?php

namespace ApiExtra;

//date_default_timezone_set(ini_get("date.timezone"));

class DTHelper
{
    private \DateTime $dt;

    public function __construct($date = null)
    {
        $this->dt = new \DateTime();

        $date = $date ?: microtime(true);
        if (is_int($date))
            $this->set_from_int($date);
        elseif (is_string($date))
            $this->set_from_string($date);
        elseif (is_object($date))
            $this->set_from_object($date);
        else
            die("UNKNOWN TYPE");
    }

    public function set_from_int($value): void
    {
        $millis_now = floor(microtime(true) * 1000);
        $millis_diff = abs($millis_now - $value);
        $seconds_now = time();
        $seconds_diff = abs($seconds_now - $value);
        
        if ($millis_diff < $seconds_diff)
            $this->set_from_millis($value);
        else
            $this->set_from_seconds($value);
    }

    public function set_from_millis($millis): void
    {
        $seconds = floor($millis / 1000);
        $this->set_from_seconds($seconds);
    }

    public function set_from_seconds($seconds): void
    {
        $this->dt->setTimestamp($seconds);
    }

    public function set_from_string($string): void
    {
        $this->dt = new \DateTime($string);
        $this->dt->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }

    public function set_from_object($object): void
    {
        $timezone = property_exists($object, 'timezone') ? $object->timezone : ini_get('date.timezone');
        $timezone = new \DateTimeZone($timezone);
        $this->dt->setTimezone($timezone);

        $year = property_exists($object, 'year') ? $object->year : intval($this->dt->format('Y'));
        $month = property_exists($object, 'month') ? $object->month : intval($this->dt->format('n'));
        $day = property_exists($object, 'day') ? $object->day : intval($this->dt->format('j'));
        $this->dt->setDate($year, $month, $day);

        $hour = property_exists($object, 'hour') ? $object->hour : intval($this->dt->format('G'));
        $minute = property_exists($object, 'minute') ? $object->minute : intval($this->dt->format('i'));
        $second = property_exists($object, 'second') ? $object->second : intval($this->dt->format('s'));
        $this->dt->setTime($hour, $minute, $second);
    }

    public function to_iso(): string
    {
        return $this->dt->format('c');
    }

    public function to_user(): string
    {
        return $this->dt->format('Y-m-d H:i:s T');
    }
}

?>