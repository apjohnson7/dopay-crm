<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    protected $fillable = ['name', 'type', 'body', 'version', 'updated_by'];

    /** Replace {{variable}} placeholders with values. */
    public function render(array $vars): string
    {
        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn ($m) => (string) ($vars[$m[1]] ?? $m[0]), $this->body);
    }
}
