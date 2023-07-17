<?php

class Parser {
    public function main() : void {
        $data = $this->readCsv();
    }

    protected function readCsv() : array {
        $filepath = "./reviews.csv";
        $fp = fopen($filepath, 'r');

        $columns = fgetcsv($fp, 4096);
        $data = [];
        while($line = fgetcsv($fp, 4096)) {
            $line = array_map("strip_tags", $line);
            $line = array_map("trim", $line);
            $line = array_combine($columns, $line);
            $data[] = $line;
        }
        //var_dump($data);
        return $data;
    }
}

$parser = new Parser();
$parser->readCsv();
