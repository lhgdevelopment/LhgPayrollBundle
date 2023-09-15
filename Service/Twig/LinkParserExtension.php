<?php

namespace KimaiPlugin\LhgPayrollBundle\Service\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class LinkParserExtension extends AbstractExtension
{
    public function getFilters()
    {
        return [
            new TwigFilter('parse_links', [$this, 'parseLinks'], ['is_safe' => ['html']]),
        ];
    }

    public function parseLinks($text)
    {
        $pattern = '/\b(https?:\/\/\S+)/i'; 
        $textWithLinks = preg_replace($pattern, '<a href="$1" target="_blank">$1</a>', $text);

        return $textWithLinks; 
    }
}