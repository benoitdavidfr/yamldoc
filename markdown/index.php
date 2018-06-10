<?php
// test Markdown

//use Michelf\Markdown;
//require_once __DIR__."/PHPMarkdownLib1.8.0/Michelf/Markdown.inc.php";

//echo Markdown::defaultTransform(file_get_contents('text.md'));

require_once __DIR__."/PHPMarkdownLib1.8.0/Michelf/MarkdownExtra.inc.php";
use Michelf\MarkdownExtra;
echo MarkdownExtra::defaultTransform(file_get_contents('text.md'));
