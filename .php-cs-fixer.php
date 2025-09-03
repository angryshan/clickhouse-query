<?php

$header = '';

return (new \PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR2' => true,
        '@Symfony' => true,
        '@DoctrineAnnotation' => true,
        '@PhpCsFixer' => true,
        'header_comment' => [
            'comment_type' => 'PHPDoc',
            'header' => $header,
            'separate' => 'none',
            'location' => 'after_declare_strict',
        ],
        'array_syntax' => [
            'syntax' => 'short',
        ],
        'list_syntax' => [
            'syntax' => 'short',
        ],
        'concat_space' => [
            'spacing' => 'one',
        ],
        'blank_line_before_statement' => [
            'statements' => [
                'declare',
            ],
        ],
        
        'ordered_imports' => [
            'imports_order' => [
                'class', 'function', 'const',
            ],
            'sort_algorithm' => 'alpha',
        ],
        'single_line_comment_style' => [
            'comment_types' => [
            ],
        ],
        'yoda_style' => [
            'always_move_variable' => false,
            'equal' => false,
            'identical' => false,
        ],
        'phpdoc_align' => [
            'align' => 'left',
        ],
        'multiline_whitespace_before_semicolons' => [
            'strategy' => 'no_multi_line',
        ],
        'constant_case' => [
            'case' => 'lower',
        ],
        'class_attributes_separation' => true,
        'combine_consecutive_unsets' => true,
        'declare_strict_types' => true,
        'linebreak_after_opening_tag' => true,
        'lowercase_static_reference' => true,
        'no_useless_else' => true,
        'no_unused_imports' => false,  // 禁用，避免错误删除类型声明用的import
        'not_operator_with_space' => false,
        'ordered_class_elements' => true,
        'php_unit_strict' => false,
        'phpdoc_separation' => false,
        'single_quote' => true,
        'standardize_not_equals' => true,
        'multiline_comment_opening_closing' => true,
        
        // 禁用过度删除PHPDoc的规则
        'no_superfluous_phpdoc_tags' => false,        // 保留@param、@return等注释
        'phpdoc_summary' => false,                     // 不强制注释末尾加句号
        'phpdoc_no_package' => false,                  // 保留@package标签
        'general_phpdoc_annotation_remove' => false,  // 不删除任何PHPDoc注释
        'not_operator_with_successor_space' => false, // 不在!后面强制加空格
    ])
    ->setFinder(
        \PhpCsFixer\Finder::create()
            ->exclude('test')           // 排除测试文件
            ->exclude('public')
            ->exclude('runtime')
            ->exclude('vendor')
            ->notName('example.php')    // 排除示例文件
            ->notPath('publish/')       // 排除发布配置
            ->in(__DIR__ . '/src')      // 只处理src目录
    )
    ->setLineEnding("\r\n")
    ->setUsingCache(false)
    ;
