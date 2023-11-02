<?php


namespace Abd\Larahelpers\Traits;

trait ModelRandom
{
    public static function randomBuilder($conditions = [])
    {
        $data = collect();
        $count = static::where($conditions)->count();
        if($count < 100) $data = static::where($conditions)->get();
        else $data = static::where($conditions)->limit(100)->get();
        return new RandomModel($data, $count);
    }
}

class RandomModel
{
    public function __construct(protected $data, protected $count)
    {
    }

    public function random()
    {
        if($this->count == 0) return null;
        else return $this->data[rand(0, $this->count - 1)];
    }

    public function toArray()
    {
        return $this->data->toArray();
    }
}
