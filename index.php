<?php

define('CLIENT_SECRET', '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');
define('CLIENT_ID', '232548498662-1u2kukubjr3fq98odt92f73eijg8jrdo.apps.googleusercontent.com');
define('REFRESH_TOKEN', '1//0cisOiCxTH3pQCgYIARAAGAwSNwF-L9Irz1hTcmgrKe5PP-oJLGGGNXZnCM7r_ab_-eS-uSIotiqEZ1b7ua0quivxWPe5ETUrP8Q');

class Parser {
    protected string $filepath;
    protected $conn;
    protected $accessToken;

    public function __construct(string $filepath) {
        $this->filepath = $filepath;
        $this->conn = curl_init();
    }

    public function run() : void {
        $data = $this->readCsv();
        $this->setGoogleToken();
        $data = $this->setReviewScores($data);
        uasort($data, array($this, 'sortByScoreDesc'));
        $bestReview = $data[0];
        $worstReview = end($data);
        $this->showResults($bestReview, $worstReview);
        $this->analizeSentiment($data[0]['description']);
        curl_close($this->conn);
    }

    protected function readCsv() : array {
        $fp = fopen($this->filepath, 'r');

        $columns = fgetcsv($fp, 4096);
        $data = [];
        while($line = fgetcsv($fp, 4096)) {
            $line = array_map("strip_tags", $line);
            $line = array_map("trim", $line);
            $line = array_combine($columns, $line);
            $data[] = $line;
        }
        return $data;
    }

    protected function setCurl($url, $headers, $post = true, $input = null) : void {

        curl_setopt($this->conn, CURLOPT_URL, $url);
        curl_setopt($this->conn, CURLOPT_RETURNTRANSFER, true);
        if($post) {
            curl_setopt($this->conn, CURLOPT_POST, 1);
            curl_setopt($this->conn, CURLOPT_POSTFIELDS, $input);
        }
        else curl_setopt($this->conn, CURLOPT_HTTPGET, 1);
        curl_setopt($this->conn, CURLOPT_HTTPHEADER, $headers);
    }

    protected function analizeSentiment(string $line) : string {
        $url = "https://language.googleapis.com/v1/documents:analyzeSentiment";

        $input = [
            'encodingType' => 'UTF8',
            'document' => [
                'type' => 'PLAIN_TEXT',
                'content' => $line
            ]
        ];

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ];
        $this->setCurl($url, $headers, true, json_encode($input));
        try{
            $data = curl_exec($this->conn);
            return json_decode($data, true)['documentSentiment']['score'];
        } catch(Exception $e) {
            exit("Failed executing API request");
        }
    }

    protected function setGoogleToken() : void {
        $headers = [
            'Content-Type: application/x-www-form-urlencoded'
        ];
        $url = 'https://www.googleapis.com/oauth2/v4/token';

        $input = [
            'refresh_token' => REFRESH_TOKEN,
            'client_id' => CLIENT_ID,
            'client_secret' => CLIENT_SECRET,
            'grant_type' => 'refresh_token',
            'redirect_uri' => 'http://localhost',
        ];
        $this->setCurl($url, $headers, true, http_build_query($input));
        $response = curl_exec($this->conn);
        $data = json_decode($response, true);
        if(!isset($data['access_token'])){
            exit("Failed authorizing Google API. Try to update refresh token");
        }

        $this->accessToken = $data['access_token'];
    }

    protected function setReviewScores(array $data) : array {
        foreach($data as $index => $review) {
            $data[$index]['score'] = $this->analizeSentiment($review['description']);
        }
        return $data;
    }

    protected function sortByScoreAsc($a, $b) {
        if ($a['score'] == $b['score']) {
            return 0;
        }
        return ($a['score'] < $b['score']) ? -1 : 1;
    }

    protected function sortByScoreDesc($a, $b) {
        if ($a['score'] == $b['score']) {
            return 0;
        }
        return ($a['score'] > $b['score']) ? -1 : 1;
    }

    protected function showResults($best, $worst) {
        echo "The best review: \"{$best['name']}\" \"{$best['description']}\"" . PHP_EOL;
        echo '=========' . PHP_EOL;
        echo "The worst review: \"{$worst['name']}\" \"{$worst['description']}\"" . PHP_EOL;
    }
}

if(!isset($argv[1])) {
    exit("Filepath argument is required!");
}
$parser = new Parser($argv[1]);
$parser->run();
