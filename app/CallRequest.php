<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CallRequest extends Model
{
    public function originates()
    {
        return $this->hasMany('App\Originate');
    }
}
