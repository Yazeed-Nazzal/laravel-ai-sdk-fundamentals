<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['support_conversation_id', 'role', 'content'])]
class SupportMessage extends Model {}
