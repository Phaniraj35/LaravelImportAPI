<?php

namespace LadyBird\StreamImport;


use DB;

class CsvImporter
{
    /**
     * Import method used for saving file and importing it using a database query
     * 
     * @param Symfony\Component\HttpFoundation\File\UploadedFile $csv_import
     * @return int number of lines imported
     */
    public function import($csv_import,$table)
    {
        // Save file to temp directory
        $this->moved_file = $this->moveFile($csv_import);

        // Normalize line endings
        $this->normalized_file  =  $this->normalize($this->moved_file);

        // Import contents of the file into database
        return $this->importFileContents($this->normalized_file,$table);
    }

    /**
     * Move File to a temporary storage directory for processing
     * temporary directory must have 0755 permissions in order to be processed
     *
     * @param Symfony\Component\HttpFoundation\File\UploadedFile $csv_import
     * @return Symfony\Component\HttpFoundation\File $moved_file
     */
    public function moveFile($csv_import)
    {
        // Check if directory exists make sure it has correct permissions, if not make it
        if (is_dir($this->destination_directory = storage_path('imports/tmp'))) {
            chmod($this->destination_directory, 0755);
        } else {
            mkdir($this->destination_directory, 0755, true);
        }

        // Get file's original name
        $original_file_name = $csv_import->getClientOriginalName();

        // Return moved file as File object
        return $csv_import->move($this->destination_directory, $original_file_name);
    }

    /**
     * Convert file line endings to uniform "\r\n" to solve for EOL issues
     * Files that are created on different platforms use different EOL characters
     * This method will convert all line endings to Unix uniform
     *
     * @param string $file_path
     * @return string $file_path
     */
    public function normalize($file_path)
    {
        //Load the file into a string
        $string = @file_get_contents($file_path);

        if (!$string) {
            return $file_path;
        }

        //Convert all line-endings using regular expression
        $string = preg_replace('~\r\n?~', "\n", $string);

        file_put_contents($file_path, $string);

        return $file_path;
    }

    /**
     * Import CSV file into Database using LOAD DATA LOCAL INFILE function
     *
     * NOTE: PDO settings must have attribute PDO::MYSQL_ATTR_LOCAL_INFILE => true
     *
     * @param $file_path
     * @return mixed Will return number of lines imported by the query
     */
    private function importFileContents($file_path,$table)
    {
        $query = sprintf("LOAD DATA LOCAL INFILE '%s' INTO TABLE '%s' 
            LINES TERMINATED BY '\\n'
            FIELDS TERMINATED BY ',' 
            IGNORE 1 LINES (`content`)", addslashes($file_path),$table);

        return DB::connection()->getpdo()->exec($query);
    }
}
?>