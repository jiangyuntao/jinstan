<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/config',
        __DIR__ . '/core/src',
        __DIR__ . '/plugins',
        __DIR__ . '/public',
        __DIR__ . '/site',
        __DIR__ . '/tests',
    ])
    ->name('*.php');

return (new Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
        'array_indentation' => true,
        'binary_operator_spaces' => ['default' => 'single_space'],
        'blank_line_after_opening_tag' => true,
        'braces_position' => ['control_structures_opening_brace' => 'same_line'],
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'no_extra_blank_lines' => ['tokens' => ['extra']],
        'no_multiple_statements_per_line' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'single_line_empty_body' => false,
        'single_quote' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],
    ])
    ->setFinder($finder);
