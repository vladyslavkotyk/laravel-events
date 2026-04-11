<?php

namespace App\Services;

interface PdfGenerator
{
    public function generate(string $template, array $data): string;
}
