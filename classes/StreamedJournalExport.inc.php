<?php

/**
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

class StreamedJournalExport
{
    /**
     * The metadata DOM must not contain metrics. Metrics are the last child in the journal schema.
     * Keep only the existing metadata DOM and a bounded XMLWriter buffer in memory.
     */
    public function write(DOMDocument $document, iterable $metrics, $path)
    {
        $bytes = $document->save($path);
        if ($bytes === false || $bytes === 0) {
            throw new RuntimeException('Could not write journal XML.');
        }

        $metricsPath = tempnam(dirname($path), 'fjt-metrics-');
        if ($metricsPath === false) {
            throw new RuntimeException('Could not create metrics temporary file.');
        }
        $writer = new XMLWriter();
        $input = null;
        $output = null;
        try {
            if (!$writer->openUri($metricsPath)) {
                throw new RuntimeException('Could not open metrics temporary file.');
            }
            $count = 0;
            foreach ($metrics as $metric) {
                if ($count === 0) {
                    $writer->startElementNS(null, 'metrics', $document->documentElement->namespaceURI);
                }
                $writer->startElement('metric');
                foreach (['assoc_type', 'assoc_id', 'day', 'metric', 'metric_type', 'load_id'] as $attribute) {
                    $this->writeAttribute($writer, $attribute, (string) $metric[$attribute]);
                }
                foreach (['country_id', 'region', 'city', 'file_type'] as $attribute) {
                    if (!empty($metric[$attribute])) {
                        $this->writeAttribute($writer, $attribute, (string) $metric[$attribute]);
                    }
                }
                $writer->endElement();
                $count++;
                if ($count % 1000 === 0 && $writer->flush() === false) {
                    throw new RuntimeException('Could not flush metrics XML.');
                }
            }
            if ($count === 0) {
                return;
            }
            $writer->endElement();
            if ($writer->flush() === false) {
                throw new RuntimeException('Could not flush metrics XML.');
            }
            unset($writer);

            // DOMDocument::save() produces this exact suffix for the generated journal document.
            // Verify it before replacing it; never search or replace text inside user content.
            $suffix = '</' . $document->documentElement->tagName . ">\n";
            $output = fopen($path, 'r+b');
            $input = fopen($metricsPath, 'rb');
            if (!$output || !$input || fseek($output, -strlen($suffix), SEEK_END) !== 0) {
                throw new RuntimeException('Could not append metrics to journal XML.');
            }
            if (fread($output, strlen($suffix)) !== $suffix || fseek($output, -strlen($suffix), SEEK_END) !== 0) {
                throw new RuntimeException('Unexpected journal XML closing element.');
            }
            // PHP may mmap the entire source when no length is supplied. Bound each copy explicitly.
            $copied = 0;
            while (!feof($input)) {
                $chunk = stream_copy_to_stream($input, $output, 1048576);
                if ($chunk === false || ($chunk === 0 && !feof($input))) {
                    throw new RuntimeException('Could not copy metrics XML.');
                }
                $copied += $chunk;
            }
            if ($copied !== filesize($metricsPath) || fwrite($output, $suffix) !== strlen($suffix)) {
                throw new RuntimeException('Could not finish journal XML.');
            }
            if (!fflush($output)) {
                throw new RuntimeException('Could not flush journal XML.');
            }
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            unset($writer);
            unlink($metricsPath);
        }
    }

    /** Validate the complete file without loading it into a second DOM. */
    public function validate($path, $schema)
    {
        $previousErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $reader = new XMLReader();
        try {
            // This is our generated export, which may embed large cover images as text.
            // Keep network access disabled and do not enable DTD loading or entity substitution.
            if (!$reader->open($path, null, LIBXML_NONET | LIBXML_PARSEHUGE) || !$reader->setSchema($schema)) {
                throw new RuntimeException('Could not initialize journal XML validation.');
            }
            $nodes = 0;
            while ($reader->read()) {
                // Stop early on invalid input instead of accumulating millions of libxml errors.
                if (++$nodes % 1000 === 0) {
                    $error = libxml_get_last_error();
                    if ($error && $error->level >= LIBXML_ERR_ERROR) {
                        break;
                    }
                }
            }
            $error = libxml_get_last_error();
            if (!$reader->isValid() || ($error && $error->level >= LIBXML_ERR_ERROR)) {
                throw new RuntimeException('Journal XML validation failed: ' . ($error ? trim($error->message) : 'invalid XML'));
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
    }

    private function writeAttribute(XMLWriter $writer, $name, $value)
    {
        $xmlCharacters = '/\A[\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]*\z/u';
        if (preg_match($xmlCharacters, $value) !== 1) {
            throw new RuntimeException('Invalid XML character in metric attribute: ' . $name);
        }
        if (!$writer->writeAttribute($name, $value)) {
            throw new RuntimeException('Could not write metric attribute: ' . $name);
        }
    }
}
