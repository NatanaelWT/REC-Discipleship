<?php

function import_xlsx_shared_string_text(XMLReader $reader): string
{
    if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'si' || $reader->isEmptyElement) {
        return '';
    }

    $itemDepth = $reader->depth;
    $phoneticDepth = null;
    $text = '';
    while ($reader->read()) {
        if ($reader->nodeType === XMLReader::END_ELEMENT
            && $reader->depth === $itemDepth
            && $reader->localName === 'si') {
            break;
        }
        if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'rPh') {
            $phoneticDepth = $reader->depth;

            continue;
        }
        if ($reader->nodeType === XMLReader::END_ELEMENT
            && $phoneticDepth !== null
            && $reader->depth === $phoneticDepth
            && $reader->localName === 'rPh') {
            $phoneticDepth = null;

            continue;
        }
        if ($reader->nodeType === XMLReader::ELEMENT
            && $reader->localName === 't'
            && $phoneticDepth === null) {
            $text .= $reader->readString();
        }
    }

    return $text;
}
