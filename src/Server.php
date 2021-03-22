<?php

/**
 * Server
 * Simple Twig site server - Serving up twiggy sites!
 * @version 1.0.5
 */

namespace Twigstem;

class Server
{
    private $twig;

    public function __construct($appDir = null, $debugMode = true)
    {

        // setup the twig bizness
        if (!$appDir) {
            // default directory for views and data
            $appDir = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
        }
        $appDir = rtrim($appDir, DIRECTORY_SEPARATOR);
        if (!is_dir($appDir)) {
            $err = "Invalid source directory. Twigstem needs the path to the directory containing view and data files. The directory specified ($appDir) is not available. ";
            $this->error("Error starting Twigstem", $err);
        }
        $this->dataDir = $appDir . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR;
        $viewDir = $appDir . DIRECTORY_SEPARATOR . 'views';
        // get all views
        $template_sub_dirs = glob($viewDir . '/*', GLOB_ONLYDIR); // all folders below the template directory root

        $templatelocations = array(
            $viewDir,
        );
        $templatelocations = array_merge($templatelocations, $template_sub_dirs);

        $loader = new \Twig\Loader\FilesystemLoader($templatelocations);
        if ($debugMode) {
            $this->twig = new \Twig\Environment($loader, [
                'debug' => true,
                // ...
            ]);
            $this->twig->addExtension(new \Twig\Extension\DebugExtension());
        } else {
            $this->twig = new \Twig\Environment($loader);
        }

        // add custom functions
        if (class_exists('\App\TwigExtension')) {
            $this->twig->addExtension(new \App\TwigExtension());
        }
    }

    /**
     * Serve up some content using a template and data
     *
     * @param null $startTemplateName
     * @param array $data
     */
    public function serve($startTemplateName = null, $data = array())
    {
        $this->status = "200 OK";
        $output = $this->loadAndRender($startTemplateName, $data);
        $this->send($output);
    }

    /**
     * send the output to the browser
     * @param $output
     */
    public function send($output)
    {
        if (!headers_sent()) {
            $protocol = $_SERVER["SERVER_PROTOCOL"];
            if (('HTTP/1.1' != $protocol) && ('HTTP/1.0' != $protocol))
                $protocol = 'HTTP/1.0';
            header("$protocol $this->status");
            header('Content-type: text/html; charset=utf-8');
            header("Cache-Control: no-cache, must-revalidate");
            header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
            header("Pragma: no-cache");
        }
        echo $output;
        exit();

        //use Symfony\Component\HttpFoundation\Response;
//        $response = new Response();
//        $response->setContent($output);
//        $response->setStatusCode(Response::HTTP_OK);
//        $response->headers->set('Content-Type', 'text/html');
//        $response->send();
    }

    /**
     * Load and render a template.
     *
     * @param type $startTemplateName (optional)
     * @param array $data
     * @return string html output
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
            if ($startTemplateName == 'error.twig') {
                $this->status = "404 Not Found";
                return $this->error('Error', $data['error']);
            } else {
                $this->status = "404 Not Found";
                return $this->loadAndRender('error.twig', ['error' => $e->getMessage()]);
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

    private function error($title = "Error", $msg = "Unknown error")
    {
        $this->status = '500 Internal Server Error';
        $output = '<!DOCTYPE html>
<html>
    <head>
        <title>Fatal Error</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            button,html,input,select,textarea{color:#222}html{font:13px/1.4 Verdana,Arial,Helvetica,sans-serif}hr{display:block;height:1px;border:0;border-top:1px solid #ccc;margin:1em 0;padding:0}audio,canvas,img,video{vertical-align:middle}fieldset{border:0;margin:0;padding:0}textarea{resize:vertical}.container{max-width:720px;margin:0 auto;padding:0 10px}.ir{background-color:transparent;border:0;overflow:hidden;text-indent:-9999px}.ir:before{content:"";display:block;width:0;height:150%}.hidden{display:none!important;visibility:hidden}.visuallyhidden{border:0;clip:rect(0000);height:1px;overflow:hidden;position:absolute;width:1px;margin:-1px;padding:0}.visuallyhidden.focusable:active,.visuallyhidden.focusable:focus{clip:auto;height:auto;overflow:visible;position:static;width:auto;margin:0}.invisible{visibility:hidden}.clearfix:after,.clearfix:before{content:" ";display:table}.clearfix:after{clear:both}.clearfix{zoom:1}::-moz-selection,::selection{background:#b3d4fc;text-shadow:none}@media print{*{background:transparent!important;color:#000!important;box-shadow:none!important;text-shadow:none!important}a,a:visited{text-decoration:underline}a[href]:after{content:" (" attr(href) ")"}abbr[title]:after{content:" (" attr(title) ")"}.ir a:after,a[href^="javascript:"]:after,a[href^="#"]:after{content:""}blockquote,pre{border:1px solid #999;page-break-inside:avoid}thead{display:table-header-group}img,tr{page-break-inside:avoid}img{max-width:100%!important}h2,h3,p{orphans:3;widows:3}h2,h3{page-break-after:avoid}}
            body{color: #444} .error h1{color:#d51a16}        
        </style>
    </head>
    <body>
        <div class="container error" style="padding: 2rem;">
            <h1 >' . $title . '</h1>
            <hr />
            <div class="error-content">
                ' . $msg . '
                
            </div>
        </div>

    </body>
</html>';
        $this->send($output);
    }

}