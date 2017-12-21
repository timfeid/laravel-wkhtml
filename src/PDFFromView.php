<?php

namespace TimFeid\LaravelWkhtml;

use Illuminate\View\View;

class PDFFromView
{
    const CONF_DIR_NAME = 'WKHTMLTOPDF_BIN_DIR';

    protected $bodyHtml;
    protected $headerHtml;
    protected $footerHtml;
    protected $marginLeft = '0mm';
    protected $marginRight = '0mm';
    protected $marginTop = '0mm';
    protected $marginBottom = '0mm';
    protected $headerSpacing = 0;
    protected $headerFile;
    protected $footerFile;
    protected $storagePath;
    protected $tempName;
    protected $cleanupFiles = [];
    protected $bin;
    protected $javascriptDelay = 0;
    protected $dpi = 300;
    protected $viewportSize = '1920x1080';

    public function __construct($view, $data = [])
    {
        $this->loadView($view, $data);
        $this->tempName = uniqid(true).time();
        $this->storagePath = storage_path('app');
        if (env(self::CONF_DIR_NAME)) {
            $this->bin = rtrim(env(self::CONF_DIR_NAME), '/').'/';
        }
    }

    protected function loadView($view, $data)
    {
        if (is_string($view)) {
            $view = view($view, $data);
        }

        $this->bodyHtml = $view->render();
    }

    public function setMargins($margin)
    {
        $this->setXMargins($margin);
        $this->setYMargins($margin);

        return $this;
    }

    public function setMarginX($margin)
    {
        $this->setMarginLeft($margin);
        $this->setMarginRight($margin);

        return $this;
    }

    public function setMarginY($margin)
    {
        $this->setMarginTop($margin);
        $this->setMarginBottom($margin);

        return $this;
    }

    public function setMarginLeft($margin)
    {
        $this->marginLeft = $margin;

        return $this;
    }

    public function setMarginRight($margin)
    {
        $this->marginRight = $margin;

        return $this;
    }

    public function setMarginTop($margin)
    {
        $this->marginTop = $margin;

        return $this;
    }

    public function setJavascriptDelay($delay)
    {
        $this->javascriptDelay = $delay;

        return $this;
    }

    public function setDpi($dpi)
    {
        $this->dpi = $dpi;

        return $this;
    }

    public function setViewportSize($viewportSize)
    {
        $this->viewportSize = $viewportSize;

        return $this;
    }

    public function setMarginBottom($margin)
    {
        $this->marginBottom = $margin;

        return $this;
    }

    public function withHeaderFile($file)
    {
        $this->headerFile = $file;

        return $this;
    }

    public function withFooterFile($file)
    {
        $this->footerFile = $file;

        return $this;
    }

    public function setHeaderSpacing($spacing)
    {
        $this->headerSpacing = $spacing;

        return $this;
    }

    public function generateViewProperty($property, $view, $data = [])
    {
        if (in_array($property, ['header', 'footer'])) {
            if (is_string($view)) {
                $view = view($view, $data);
            }

            $property = "{$property}File";
            $this->$property = $this->storagePath("{$this->tempName}{$property}.html");
            $this->cleanupFiles[] = $this->$property;
            file_put_contents($this->$property, $view->render());
        }

        return $this;
    }

    public function withHeaderView($view, $data = [])
    {
        $this->generateViewProperty('header', $view, $data);

        return $this;
    }

    public function withFooterView($view, $data = [])
    {
        $this->generateViewProperty('footer', $view, $data);

        return $this;
    }

    public function stream($filename = 'document.pdf')
    {
        return $this->response('inline', $filename);
    }

    public function download($filename = 'document.pdf')
    {
        return $this->response('attachment', $filename);
    }

    protected function response($type, $filename)
    {
        $output = $this->exec();

        return response($output, 200, array(
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $type.'; filename="'.$filename.'"',
        ));
    }

    protected function exec()
    {
        $output = shell_exec($this->command());
        // $this->cleanUp();

        return $output;
    }

    protected function cleanUp()
    {
        foreach ($this->cleanupFiles as $file) {
            unlink($file);
        }
    }

    protected function storagePath($file = null)
    {
        return $this->storagePath.DIRECTORY_SEPARATOR.$file;
    }

    protected function options()
    {
        $options = [
            '--margin-left '.$this->marginLeft,
            '--margin-right '.$this->marginRight,
            '--margin-top '.$this->marginTop,
            '--margin-bottom '.$this->marginBottom,
            '--header-spacing '.$this->headerSpacing,
            '--no-stop-slow-scripts',
            "--viewport-size {$this->viewportSize}",
            "--dpi {$this->dpi}",
            "--javascript-delay {$this->javascriptDelay}",
            "--debug-javascript",
        ];

        if ($this->headerFile) {
            $options[] = '--header-html '.$this->headerFile;
        }

        if ($this->footerFile) {
            $options[] = '--footer-html '.$this->footerFile;
        }

        return implode(' ', $options);
    }

    protected function generateFile()
    {
        $file = $this->storagePath("{$this->tempName}body.html");
        file_put_contents($file, $this->bodyHtml);

        return $file;
    }

    protected function command($to_file = '-')
    {
        $options = $this->options();
        $file = $this->generateFile();

        return die("{$this->bin}wkhtmltopdf $options {$file} $to_file");
    }
}
