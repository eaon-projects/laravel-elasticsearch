<?php
namespace App\Extensions;

/**
 * usage StopWatch::make()->start()->stop()->diff();
 * Created by PhpStorm.
 * User: chicv
 * Date: 19/4/4
 * Time: 下午3:47
 */
class StopWatch
{
    private function __construct(){

    }

    public static function make(){
        return new self();
    }

    protected $startTimestamp;
    protected $stopTimestamp;

    protected function getTimestamp(){
        return microtime(true);
    }

    public function start(){
        $this->startTimestamp = $this->getTimestamp();
        return $this;
    }

    public function stop(){
        $this->stopTimestamp = $this->getTimestamp();
        return $this;
    }

    public function diff(){
        return (round($this->stopTimestamp - $this->startTimestamp, 3) * 1000);
    }
}