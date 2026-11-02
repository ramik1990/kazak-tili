<?php

namespace App\Services\Textbook;

use RuntimeException;
use ZipArchive;

class DocxDocument
{
    private const NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private \DOMDocument $dom;

    private \DOMXPath $xpath;

    private array $relations = [];

    public function __construct(private readonly string $path)
    {
        $zip = new ZipArchive();

        if ($zip->open($this->path) !== true) {
            throw new RuntimeException("Не удалось открыть файл: {$this->path}");
        }

        $this->dom = new \DOMDocument();
        $this->dom->loadXML($zip->getFromName('word/document.xml'));
        $this->xpath = new \DOMXPath($this->dom);
        $this->xpath->registerNamespace('w', self::NS);

        $rels = new \DOMDocument();
        $rels->loadXML($zip->getFromName('word/_rels/document.xml.rels'));
        foreach ($rels->getElementsByTagName('Relationship') as $rel) {
            $this->relations[$rel->getAttribute('Id')] = basename($rel->getAttribute('Target'));
        }

        $zip->close();
    }

    /** @return array<int, array{type:string, text?:string, style?:string, images?:array, rows?:array}> */
    public function nodes(): array
    {
        $body = $this->xpath->query('/w:document/w:body')->item(0);
        $nodes = [];

        foreach ($body->childNodes as $child) {
            if (! $child instanceof \DOMElement) {
                continue;
            }

            if ($child->localName === 'p') {
                $text = $this->paragraphText($child);
                $images = $this->imagesOf($child);

                if ($text === '' && $images === []) {
                    continue;
                }

                $nodes[] = ['type' => 'paragraph', 'text' => $text, 'style' => $this->styleOf($child), 'images' => $images];
            }

            if ($child->localName === 'tbl') {
                $rows = $this->tableRows($child);

                if ($rows !== []) {
                    $nodes[] = ['type' => 'table', 'rows' => $rows];
                }
            }
        }

        return $nodes;
    }

    public function extractMedia(string $directory): array
    {
        $zip = new ZipArchive();
        $zip->open($this->path);
        $saved = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if (! str_starts_with($name, 'word/media/') || ! preg_match('/\.(png|jpe?g|gif)$/i', $name)) {
                continue;
            }

            $target = $directory.'/'.basename($name);
            file_put_contents($target, $zip->getFromIndex($i));
            $saved[] = basename($name);
        }

        $zip->close();

        return $saved;
    }

    private function styleOf(\DOMElement $paragraph): string
    {
        $style = $this->xpath->query('./w:pPr/w:pStyle/@w:val', $paragraph)->item(0);

        return $style?->nodeValue ?? '';
    }

    private function paragraphText(\DOMElement $paragraph): string
    {
        $parts = [];

        foreach ($this->xpath->query('.//w:t | .//w:tab | .//w:br', $paragraph) as $node) {
            $parts[] = match ($node->localName) {
                't' => $node->nodeValue,
                'tab' => ' ',
                default => ' ',
            };
        }

        return $this->clean(implode('', $parts));
    }

    private function imagesOf(\DOMElement $paragraph): array
    {
        $images = [];

        foreach ($this->xpath->query('.//*[local-name()="blip"]/@*[local-name()="embed"]', $paragraph) as $attr) {
            $file = $this->relations[$attr->nodeValue] ?? null;

            if ($file && preg_match('/\.(png|jpe?g|gif)$/i', $file)) {
                $images[] = $file;
            }
        }

        return $images;
    }

    private function tableRows(\DOMElement $table): array
    {
        $rows = [];

        foreach ($this->xpath->query('./w:tr', $table) as $row) {
            $cells = [];

            foreach ($this->xpath->query('./w:tc', $row) as $cell) {
                $lines = [];

                foreach ($this->xpath->query('./w:p', $cell) as $paragraph) {
                    $text = $this->paragraphText($paragraph);

                    if ($text !== '') {
                        $lines[] = $text;
                    }
                }

                $cells[] = implode(' ', $lines);
            }

            if (array_filter($cells, fn ($c) => $c !== '') !== []) {
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    private function clean(string $text): string
    {
        $text = str_replace(["\u{00A0}", "\u{200B}", "\u{FEFF}"], [' ', '', ''], $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
