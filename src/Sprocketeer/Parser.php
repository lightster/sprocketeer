<?php

namespace Sprocketeer;

use Exception;

class Parser
{
    protected $paths;
    protected $debug = true;

    public function __construct($paths)
    {
        $this->paths = $paths;
    }

    public function getPathInfoFromManifest($manifest, $read_manifest = true)
    {
        $path_info     = $this->getPathInfo($manifest);
        $absolute_path = $path_info['absolute_path'];

        if (!$read_manifest) {
            return array($path_info);
        }

        // Get only the header, we don't want any requires after that
        preg_match(
            "/^(
                (\s*) (
                    \* .* |
                    \/\/ .* |
                    \# .*
                )
            )+/mx",
            file_get_contents($absolute_path),
            $header
        );

        if (!$header) {
            return array($path_info);
        }

        $lines = explode("\n", $header[0]);

        $files = array();
        $self_required = false;
        foreach ($lines as $line) {
            if (!preg_match("/^\W*=\s*(\w+)\s*(.*)$/", $line, $line_matches)) {
                continue;
            }
            $directive = $line_matches[1];
            $require_manifest = $line_matches[2];
            switch ($directive) {
                case 'require':
                    $sub_manifest = null;
                    if ('/' === substr($require_manifest, 0, 1)) {
                        $sub_manifest = substr($require_manifest, 1);
                    } else {
                        $sub_manifest = dirname($manifest) . '/' . $require_manifest;
                    }
                    $sub_files = $this->getPathInfoFromManifest($sub_manifest);
                    $files = array_merge($files, $sub_files);
                    break;
                case 'require_self':
                    $files[] = $path_info;
                    $self_required = true;
                    break;
            }
        }

        if (!$self_required) {
            $files[] = $path_info;
        }

        return $files;
    }

    public function getPathInfo($manifest)
    {
        list($category_path_name, $filename) = explode('/', $manifest, 2);
        if (!isset($this->paths[$category_path_name])) {
            throw new Exception("Unknown category path name: '{$category_path_name}'.");
        }

        $category_path = $this->paths[$category_path_name];
        $full_path   = "{$category_path}/{$filename}";
        if (!file_exists($full_path)) {
            throw new Exception("File could not be found: {$full_path}");
        }

        $real_absolute_path  = realpath($full_path);
        $real_category_path  = realpath($category_path);
        $real_requested_path = ltrim(
            str_replace($real_category_path, '', $real_absolute_path),
            '/'
        );

        return array(
            'absolute_path'      => $real_absolute_path,
            'category_path_name' => $category_path_name,
            'category_path'      => $real_category_path,
            'requested_asset'    => $real_requested_path,
            'sprocketeer_path'   => $category_path_name . '/' . $real_requested_path,
        );
    }
}
