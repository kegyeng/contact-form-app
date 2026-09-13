<?php

namespace App\Http\Requests;

class ExportContactRequest extends IndexContactRequest
{
    protected $redirectRoute = 'admin.index';
}
