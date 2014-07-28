<?php
/*
 * how to preserve your translations:
 * just export all translations you finished language bu language;
 * and when import, use this script
 */

/*
 * Author: Wei
 * Function:This class is used to map translations to each job, should have all translations prepared first, only one language at a time, 
 *          put each language in one folder named this language, and call function for each folder
 * Date: April 1
 */

class Trans_mapper {

    private $hashtable = array(); //store source name as key, array(job id, encrypt code) as value
    private $dirname;//solution folder name
    private $zipname;//solution compressed file name, final name
    private $min;
    private $max;

    public function __construct() {
        ;
    }

    public function scanTasks($tasks_folder) {
        //will create and return hashtable for mapping
        try {
            $tasksArray = $this->readZip($tasks_folder);
//            print_r($tasksArray);
            foreach ($tasksArray as $task_folder) {
//                echo $task_folder;
                $handle = opendir($task_folder);
                $flag = 1;

                while (false !== ($filename = readdir($handle))) {
//                    echo $filename;
                    if (pathinfo($filename, PATHINFO_EXTENSION) !== 'xliff') {//only deal with .xliff files
//                        echo $filename;
//                        if (is_dir($filename)) {
//                            continue;
//                        } else {
//                            //copy all additional files to addition folder and then remove them
////                            $des = $path = getcwd() . '/' . 'addition/';
////                            copy($filename, $des . $filename);
//                            unlink($task_folder . '/' . $filename);
//                            continue;
//                        }
                        continue;
                    }

                    //match the job id use pattern 'job-xxx'
                    $pattern = '@(job-)[0-9]+@';
                    preg_match($pattern, $filename, $matches);
                    $job_id = substr($matches[0], 4); //get the job id

                    if (1 == $flag) {
                        $this->dirname = pathinfo($filename)['filename'];
                        $this->min = $this->max = $job_id;
                        $flag = 0;
                    } else {
                        if ($job_id < $this->min)
                            $this->min = $job_id;
                        if ($job_id > $this->max)
                            $this->max = $job_id;
                    }


                    //convert xliff file into object
                    $source_data = simplexml_load_file($task_folder . '/' . $filename);
                    $code = $source_data->file['original'][0];
                    $source_name = $source_data->file->body->{'trans-unit'}[0]->source;
                    $language_code = $source_data->file['target-language'][0]; //target language code

                    $key = strval($source_name);
                    $value = strval($code);
                    $language_code_target = strval($language_code);

                    //create the hashtable
                    $this->hashtable[$key] = array($job_id, $value, $language_code_target);
                    unlink($task_folder . '/' . $filename);
                }

                closedir($task_folder);
                rmdir($task_folder);
            }
//            print_r($this->hashtable);
            $this->handleTrans('translations');
        } catch (Exception $ex) {
            echo $ex->getMessage();
        }
    }

    private function buildFolder() {
        $back_part = 'job-' . $this->min . '-' . $this->max . '.zip Folder';
        $zip_back_part = 'job-' . $this->min . '-' . $this->max . '.zip';
        $front_part = strstr($this->dirname, 'job-', true);
        $this->dirname = $front_part . $back_part;
        $this->zipname = $front_part . $zip_back_part;
//        echo 'Soluton dir: ' . $this->dirname . '<br>';
        echo 'Solution zip should be : <h2>' . $this->zipname . '</h2><br>';
    }

    private function handleTrans($trans_folder) {

        try {
            $transArray = $this->readZip($trans_folder);
//            print_r($transArray);
            foreach ($transArray as $tran_folder) {

                //recursively read the folder and parse each translation
                $handle = opendir($tran_folder);
                $flag = 1;

                while (false !== ($filename = readdir($handle))) {
                    if (pathinfo($filename, PATHINFO_EXTENSION) !== 'xliff')//only deal with .xliff files 
                        continue;

                    if (1 == $flag) {
                        $this->buildFolder();
                        mkdir($this->dirname);
                        $flag = 0;
                    }

                    $source_data = simplexml_load_file($tran_folder . '/' . $filename);
                    $source_name_trans = strval($source_data->file->body->{'trans-unit'}[0]->source);
                    $target_language_code = strval($source_data->file['target-language'][0]);

                    if (key_exists($source_name_trans, $this->hashtable) && $this->hashtable[$source_name_trans][2] == $target_language_code) {

                        $source_data->file->attributes()['original'] = $this->hashtable[$source_name_trans][1]; //modify job id and encrypt code
//                    echo $source_data->file['original'][0];

                        $xml = $source_data->asXML();

                        $pattern = '@(job-)[0-9]+@';
                        $replacement = 'job-' . $this->hashtable[$source_name_trans][0];
                        $writeToFolder_filename = preg_replace($pattern, $replacement, $filename);
                        if ($this->writeToFolder($writeToFolder_filename, $xml)) {
                            echo 'file: ' . $source_name_trans . ' has been successfully transalted!<br>';
                        }
                        unset($this->hashtable[$source_name_trans]);
                    }
                    //delete each file after scanning
                    unlink($tran_folder . '/' . $filename);
                }
//            print_r($this->hashtable);
                if (!empty($this->hashtable)) {
                    foreach (array_keys($this->hashtable) as $task) {
                        echo 'file: ' . $task . ' has no translation!<br>';
                    }
                }
                //compress the solution folder and delete folder, produce zip file for uploading
                $this->compressFolder();
//                echo 'Tasks folder: ' . $this->dirname . ' has been done!<br>';

                closedir($tran_folder);
                rmdir($tran_folder);
            }//added new line here
        } catch (Exception $ex) {
            echo $ex->getMessage();
        }
    }

    private function writeToFolder($filename, $xml) {
        try {
            file_put_contents($this->dirname . '/' . $filename, $xml);
//            $this->compressFolder();
            return TRUE;
        } catch (Exception $ex) {
            echo $ex->getMessage();
        }
    }

    private function compressFolder() {

        $zip = new ZipArchive();
        if ($zip->open($this->zipname, ZipArchive::OVERWRITE) === TRUE) {
            $this->addFileToZip($this->dirname, $zip); //handle target dir and pass files into zip folder
            $zip->close(); //close zip file
        }

        //remove solution folder, only keep compress file
        $this->removeFolder($this->dirname);
    }

    private function removeFolder($dirname) {
        $handle = opendir($dirname);

        while (false !== ($filename = readdir($handle))) {
            if ($filename === '.' || $filename === '..')//only deal with .xliff files 
                continue;
            unlink($dirname . '/' . $filename);
        }
        closedir($dirname);
        return rmdir($dirname);
    }

    private function addFileToZip($path, $zip) {
        $handle = opendir($path);
        while ((false !== $filename = readdir($handle))) {
            if (pathinfo($filename, PATHINFO_EXTENSION) === 'xliff') {
//                echo $filename.'<br>';
                $zip->addFile($path . "/" . $filename);
            }
        }
        closedir($path);
    }

    private function readZip($tasks_folder) {
        $taskArray = array();
        $handle = opendir($tasks_folder);
        while (FALSE !== ($filename = readdir($handle))) {
            if (pathinfo($filename, PATHINFO_EXTENSION) !== 'zip')//only deal with .zip files 
                continue;

            $path = getcwd() . '/' . $tasks_folder . '/' . pathinfo($filename)['filename'];

            $zip = new ZipArchive(); //新建一个ZipArchive的对象
            if ($zip->open($tasks_folder . '/' . $filename) === TRUE) {
                $zip->extractTo($path);
                $taskArray[] = $tasks_folder . '/' . pathinfo($filename)['filename'];
                $zip->close(); //关闭处理的zip文件
            } else {
                echo 'open file failed!';
            }
        }
//        echo 'finish!';
        closedir($tasks_folder);
        return $taskArray;
    }

}

$trans_mapper = new Trans_mapper();
//$trans_mapper->readZip('tasks');
//echo getcwd();
$trans_mapper->scanTasks('dev');
////$trans_mapper->buildFolder();
//$trans_mapper->handleTrans('translations');
//$trans_mapper->removeDir('tasks/test');
//echo $trans_mapper->removeFolder('test');
