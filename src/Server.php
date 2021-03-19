<?php

/**
 * Server
 */
namespace Twigstem;

class Server
{
    public static $DEBUGMODE = true;

    private $twig;
    private $pageData;

    public function __construct()
    {
        // setup the twig bizness
        $appDir = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR;
        $this->dataDir = $appDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR;
        $viewDir = $appDir . 'views';
        // get all views
        $template_sub_dirs = glob($viewDir . '/*', GLOB_ONLYDIR); // all folders below the template directory root

        $templatelocations = array(
            $viewDir,
        );
        $templatelocations = array_merge($templatelocations, $template_sub_dirs);
//        $ignore = array('_', '.');
//        array_filter($templatelocations, function($path){
//            $basename = $basename($path)l
//            if (!in_array($ignore, substr($path, 1))){
//                return $path;
//            }
//        })


        $loader = new \Twig\Loader\FilesystemLoader($templatelocations);
        if (self::$DEBUGMODE) {
            $this->twig = new \Twig\Environment($loader, [
                'debug' => true,
                // ...
            ]);
            $this->twig->addExtension(new \Twig\Extension\DebugExtension());
        } else {
            $this->twig = new \Twig\Environment($loader);
        }

    }

    /**
     * Load and render a template.
     * @param type $startTemplateName (optional)
     * @param array $datamiox
     * @return type
     */
    public function loadAndRender($startTemplateName = null, $data = array())
    {
        if (!$startTemplateName) {
            // if no starting template, assume there is one at the requested URL
            $url_data = @parse_url($_SERVER['REQUEST_URI']);
            // make index.html kick in automatically
            if (@$url_data['path'] == '/') {
                $url_data['path'] = 'index.twig';
            }
            $startTemplateName = @$url_data['path'];
        }

        $templateName = basename($startTemplateName, '.twig');
        try {
            $template = $this->twig->load($templateName . '.twig');
            $pageData = array_merge($data, $this->loadData($template));

            return $template->render($pageData);
        } catch (\Exception $e) {
            if ($startTemplateName == 'error.twig'){
                return "ERROR" . $e->getMessage();
            } else {

                return $this->loadAndRender('error.twig', ['error'=>$e->getMessage()]);
            }

        }
    }

    public function loadData($template)
    {
        $loadedPath = $template->getSourceContext()->getPath();

        // (1) Check if the root template has a special comment
        //$tpl = $template->getSourceContext()->getCode();
        $tpl = file_get_contents($loadedPath);
        $tokens = $this->parseTpl($tpl);

        if (count($tokens)) {
            return $tokens;
        }


        // (2) Check if there is a json file with the same name

        $pathInfo = pathinfo($loadedPath);
        $dataPath = $pathInfo['dirname'] . DIRECTORY_SEPARATOR . $pathInfo['filename'] . '.json';
        if (file_exists($dataPath)) {
            $json = file_get_contents($dataPath);
            return json_decode($json, 1);
        }
        // (3) check in the data folder
        $dataPath = $this->dataDir . $pathInfo['filename'] . '.json';
        if (file_exists($dataPath)) {
            $json = file_get_contents($dataPath);
            return json_decode($json, 1);
        }

        return array();
    }

    private function parseTpl($str)
    {
        $data = array();
        if (preg_match_all("/{#\s*data([^}]*)#}/", $str, $matches)) {
            foreach ($matches[1] as $match) {
                $matched = array_merge($data, $this->parseTplProps($match));
                if (isset($matched['src'])) {
                    // if we can find the file specified, load it
                    $dataPath = $this->dataDir . $matched['src'];
                    if (file_exists($dataPath)) {
                        $json = file_get_contents($dataPath);
                        $loaddata = json_decode($json, 1);
                        // if we have an id, attach the loaded data using this id
                        if (isset($matched['id'])) {
                            $data[$matched['id']] = $loaddata;
                        } else {
                            // otherwise merge in the 'root' data list;
                            $data = array_merge($data, $loaddata);
                        }
                    }

                }

            }
        }

        return $data;
    }

    private function parseTplProps($str)
    {
        $params = array();
        $mx = strlen($str);
        $key = '';
        $val = '';
        $scanmode = 'left';
        $exposed = false;
        $enclosure = '"'; // can be a single or double quote first encountered is used)

        for ($i = 0; $i < $mx; $i++) {
            $ch = $str[$i];
            if ($scanmode == 'left') {
                // we're looking for the key name or the equal sign

                if ($ch == ':') {
                    $scanmode = 'right';
                    $enclosed = false;
                    $exposed = false;
                } elseif ($ch <> ' ') {
                    $key .= $ch;
                }
            } else {
                // we are on the right of an assignment.
                if ($enclosed) {

                    // currently inside a quote
                    if ($ch == $enclosure) {
                        // finished with this value
                        $params[$key] = $val;
                        $scanmode = 'left';
                        $key = '';
                        $val = '';
                    } else {
                        $val .= $ch;
                    }
                } elseif ($exposed) {
                    // this value is not quoted. Exit with first space or commad

                    if (($ch == ' ') || ($ch == ',')) {
                        // finished with this value
                        $params[$key] = $val;
                        $scanmode = 'left';
                        $key = '';
                        $val = '';
                    } else {

                        $val .= $ch;
                    }
                } else {
                    // not sure what mode yet. If the first non whitespace is a quote, assume encosed;
                    // otherwise, we're exposed
                    if ($ch == '"') {
                        $enclosed = true;
                        $exposed = false;
                        $enclosure = '"';
                    } elseif ($ch == "'") {
                        $enclosed = true;
                        $exposed = false;
                        $enclosure = "'";
                    } elseif (($ch <> ' ') && ($ch <> ',')) {
                        $enclosed = false;
                        $exposed = true;
                        $val .= $ch;
                    }
                }
            }
        }
        if ($key <> '') {
            $params[$key] = $val;
        }

        return ($params);
    }


}

