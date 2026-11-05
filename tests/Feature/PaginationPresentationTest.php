<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class PaginationPresentationTest extends TestCase
{
    public function test_default_pagination_has_compact_arrows_and_correct_page_boundaries(): void
    {
        foreach ([1, 2, 3] as $page) {
            $paginator = new LengthAwarePaginator(['fila'], 61, 30, $page, ['path' => '/productos']);
            $html = (string) $paginator->links();
            $dom = new DOMDocument;
            @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
            $xpath = new DOMXPath($dom);
            $this->assertSame(1, $xpath->query('//nav[@class="pagination"]')->length);
            $this->assertSame(2, $xpath->query('//svg[@width="16" and @height="16" and @aria-hidden="true"]')->length);
            $this->assertSame($page === 1 ? 0 : 1, $xpath->query('//a[@rel="prev"]')->length);
            $this->assertSame($page === 3 ? 0 : 1, $xpath->query('//a[@rel="next"]')->length);
            $this->assertStringContainsString('Página '.$page.' de 3', $html);
            $this->assertStringNotContainsString('w-5', $html);
        }
    }

    public function test_pagination_preserves_search_filters_and_independent_page_parameter(): void
    {
        $paginator = new LengthAwarePaginator(['fila'], 100, 30, 2, ['path' => '/existencias', 'pageName' => 'units_page']);
        $paginator->appends(['q' => 'guantes', 'ubicacion_id' => 8, 'page' => 4]);
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.(string) $paginator->links());
        $xpath = new DOMXPath($dom);
        foreach (['prev' => 1, 'next' => 3] as $rel => $page) {
            parse_str(parse_url($xpath->query('//a[@rel="'.$rel.'"]')->item(0)->getAttribute('href'), PHP_URL_QUERY), $query);
            $this->assertSame(['q' => 'guantes', 'ubicacion_id' => '8', 'page' => '4', 'units_page' => (string) $page], $query);
        }
    }

    public function test_single_page_has_no_pagination_controls(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 30);
        $this->assertSame('', trim((string) $paginator->links()));
    }
}
