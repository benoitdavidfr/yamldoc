<?php
/*PhpDoc:
name: inseeapi.inc.php
title: inseeapi.inc.php - Utilisation des API INSEE
doc: <a href='/yamldoc/?action=version&name=inseeapi.inc.php'>doc intégrée en Php</a>
*/
{ // doc 
$phpDocs['inseeapi.inc.php']['file'] = <<<'EOT'
name: inseeapi.inc.php
title: inseeapi.inc.php - Utilisation des API INSEE
journal:
  21/10/2018:
    - création
EOT;
}

{ // doc 
$phpDocs['inseeapi.inc.php']['classes']['InseeApi'] = <<<'EOT'
title: classe abstraite facilitant l'accès aux API INSEE
doc: |
  La méthode getToken() renvoie le token courant.
  La méthode generateToken() en génère un nouveau.
  Nécessite l'existence du fichier inseecredentials.json contenant les champs 'key' et 'secret'
EOT;
}
abstract class InseeApi extends YamlDoc {
  static $tokenUrl = 'https://api.insee.fr/token'; // Url pour renouveller le token
  static $inseecredentialsHelp = <<<EOT
L'utilisation de cette classe nécessite une authentification sur le site https://api.insee.fr<br>
Pour cela, il faut créer un compte sur ce site et recopier la clé et le secret dans le fichier inseecredentials.json
dans les champs 'key' et 'secret'.<br><br>\n
EOT;
  protected $inseeToken = null; // le token de l'API INSEE
  
  // lecture du token dans le fichier inseetoken.json
  function getToken(): string {
    //return $this->_c['inseeToken'];
    if ($this->inseeToken)
      return $this->inseeToken;
    if (($contents = @file_get_contents(__DIR__.'/inseetoken.json')) === false) {
      $this->inseeToken = $this->generateToken(); // génération d'un nouveau token
      return $this->inseeToken;
    }
    $contents = json_decode($contents, true);
    
    $mtime = filemtime(__DIR__.'/inseetoken.json');
    //echo "fichier modifié le ",date(DATE_ATOM, $mtime),"<br>\n";
    //echo "fichier modififié il y a ",time()-$mtime," secondes<br>\n";
    if (time() - $mtime < $contents['expires_in'] - 3600) { // vérification que le token est encore valide
      if (!isset($contents['access_token']))
        throw new Exception("Erreur access_token absent du fichier inseetoken.json");
      $this->inseeToken = $contents['access_token'];
    }
    else {
      $this->inseeToken = $this->generateToken(); // génération d'un nouveau token
    }
    return $this->inseeToken;
  }
  
  // génère un nouveau token, l'enregistre dans inseetoken.json et le retourne
  // Pour cela envoie une requête à self::$tokenUrl
  function generateToken(): string {
    if (($contents = @file_get_contents(__DIR__.'/inseecredentials.json')) === false) {
      echo self::$inseecredentialsHelp;
      throw new Exception("Erreur de lecture du fichier inseecredentials.json");
    }
    $contents = json_decode($contents, true);
    if (!isset($contents['key']))
      throw new Exception("Erreur key absent du fichier inseecredentials.json");
    if (!isset($contents['secret']))
      throw new Exception("Erreur key secret du fichier inseecredentials.json");
    $auth = base64_encode("$contents[key]:$contents[secret]");
    
    $post = http_build_query([ 'grant_type' => 'client_credentials' ]);
    $context_options = [
      'http' => [
        'method' => 'POST',
        'header'=> "Content-type: application/x-www-form-urlencoded\r\n"
                    . "Content-Length: " . strlen($post) . "\r\n"
                    . "Authorization: Basic $auth\r\n",
        'content' => $post
      ]
    ];
    $context = stream_context_create($context_options);
    if (($result = @file_get_contents(self::$tokenUrl, false, $context)) === false) {
      if (isset($http_response_header)) {
        echo "<pre>http_response_header="; var_dump($http_response_header); echo "</pre>\n";
      }
      throw new Exception("Erreur dans InseeApi::generateToken() : sur url=".self::$tokenUrl);
    }
    if (@file_put_contents(__DIR__.'/inseetoken.json', $result) === false)
      throw new Exception("Erreur dans InseeApi::generateToken() écriture de inseetoken.json");
    $result = json_decode($result, true);
    return $result['access_token'];
  }
  
  function query(string $baseUrl, string $path, array $args): array {
    $token = $this->getToken();
    //die("token=$token");
    $context = stream_context_create(['http'=> ['header'=> "Authorization: Bearer $token\r\n"]]);
    $url = $baseUrl.$path;
    $noarg = 0;
    if ($args) {
      foreach ($args as $key => $value) {
        if ($noarg++ == 0)
          $url .= '?';
        else
          $url .= '&';
        $url .= $key.'='.rawurlencode($value);
      }
    }
    //die("url=$url");
    if (($result = @file_get_contents($url, false, $context)) === false) {
      if (isset($http_response_header)) {
        //echo "<pre>http_response_header="; var_dump($http_response_header); echo "</pre>\n";
        if (preg_match('!^HTTP/1\.. (\d\d\d) (.*)$!', $http_response_header[0], $matches)) {
          //print_r($matches);
          throw new Exception("Erreur HTTP $matches[1] $matches[2] dans InseeApi::query() : sur url=$url");
        }
      }
      throw new Exception("Erreur dans InseeApi::query() : sur url=$url");
    }
    return json_decode($result, true);
  }
};