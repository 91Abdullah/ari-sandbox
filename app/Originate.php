<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Originate extends Model
{
    public $incrementing = false;
    protected $primaryKey = 'id';

    public function call_request()
    {
        return $this->belongsTo('App\CallRequest');
    }
}
