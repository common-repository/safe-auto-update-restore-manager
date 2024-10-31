<?php
  if( !defined( 'ABSPATH' ) ) exit;

  // Delete Directory Function

  function autoupdaterestore_remove_directory($dir) {
    if (is_dir($dir)) { 
      $objects = scandir($dir); 
      foreach ($objects as $object) { 
        if ($object != "." && $object != "..") { 
          if (filetype($dir."/".$object) == "dir"){
            autoupdaterestore_remove_directory($dir."/".$object);
          }else{
            unlink($dir."/".$object);
          }
        } 
      } 
      reset($objects);
      rmdir($dir);
    }
  }

  // Database Backup code
  function autoupdaterestore_backup_tables($host,$user,$pass,$name,$tables = '*')
  {
    $con = mysqli_connect($host,$user,$pass);
    mysqli_select_db($con,$name);
    
    //get all of the tables
    if($tables == '*')
    {
      $tables = array();
      $result = mysqli_query($con,'SHOW TABLES');
      while($row = mysqli_fetch_row($result))
      {
        $tables[] = $row[0];
      }
    }
    else
    {
      $tables = is_array($tables) ? $tables : explode(',',$tables);
    }
    $return = '';
    //cycle through
    foreach($tables as $table)
    {
      $result = mysqli_query($con,'SELECT * FROM '.$table);
      $num_fields = mysqli_num_fields($result);
      
      $return.= 'DROP TABLE '.$table.';';
      $row2 = mysqli_fetch_row(mysqli_query($con,'SHOW CREATE TABLE '.$table));
      $return.= "\n\n".$row2[1].";\n\n";
      
      for ($i = 0; $i < $num_fields; $i++) 
      {
        while($row = mysqli_fetch_row($result))
        {
          $return.= 'INSERT INTO '.$table.' VALUES(';
          for($j=0; $j < $num_fields; $j++) 
          {
            $row[$j] = addslashes($row[$j]);
            //$row[$j] = preg_replace("\n","\\n",$row[$j]);
            if (isset($row[$j])) { $return.= '"'.$row[$j].'"' ; } else { $return.= '""'; }
            if ($j < ($num_fields-1)) { $return.= ','; }
          }
          $return.= ");\n";
        }
      }
      $return.="\n\n\n";
    }
    $date = date('Y-m-d');
    //save file
    $upload = wp_upload_dir();
    $path = $upload['basedir'].'/autoupdaterestore_backup/'.$date.'/db-backup-'.time().'-'.(md5(implode(',',$tables))).'.sql';
    $handle = fopen($path,'w+');
    
    fwrite($handle,$return);
    fclose($handle);
  }

  function autoupdaterestore_create_full_backup(){

    $posts = new WP_Query('post_type=any&posts_per_page=-1&post_status=publish');
    $posts = $posts->posts;

    $upload = wp_upload_dir();
    $folderpath = $upload['basedir'].'/autoupdaterestore_backup/'.date('Y-m-d') . '/';

    if(!file_exists($folderpath)) {
      $upload = wp_upload_dir();
      $backup_folder = $upload['basedir'].'/autoupdaterestore_backup/';

      if(!file_exists($backup_folder)) {
          mkdir($backup_folder,0777);
      }
      mkdir($folderpath,0777);
    }

    global $wpdb;

    $table = $wpdb->prefix.'autoupdaterestore_backup';
    $datetime = date('Y-m-d H:i:s');

    $backup_table = $wpdb->prefix.'autoupdaterestore_data';

    $plugin = $wpdb->get_results( "SELECT * FROM $backup_table WHERE type='sitemap'" );

    $counter = 0;

    foreach ($plugin as $pages) {
      $counter++;
      $sitemap_url = $pages->plugin_name;

      $exploded_url = explode('/', $sitemap_url);
      array_pop($exploded_url);
      $fnl_url = implode('/',$exploded_url);

      preg_match("/[^\/]+$/", $fnl_url, $matches);
      $last_word = $matches[0];

      $handle = fopen($fnl_url.'/', 'r');
      $content_from_url = stream_get_contents($handle);

      $filename = $last_word.'-'.date("Ymdhis").'.php';
      $file = $folderpath.$filename;

        $open = fopen( $file, "w+" );
        $write = fputs( $open,$content_from_url."<!-- This content is from Autoupdate Plugins & Themes -->");
        fclose( $open );

        $data = array('file_name' => $filename,'datetime'=>$datetime );
        $wpdb->insert($table,$data);
    }

    $dbname = constant('DB_NAME');
    $dbuser = constant('DB_USER');
    $dbpass = constant('DB_PASSWORD');
    $dbhost = constant('DB_HOST');

    autoupdaterestore_backup_tables($dbhost,$dbuser,$dbpass,$dbname);
  }

  function autoupdaterestore_update_restore_plugin($slug,$version){
    $folder_name = $slug;
    $download_url = "https://downloads.wordpress.org/plugin/".$slug;

    if($version != ''){
      $download_url .= ".".$version.".zip";
    }
    else{
      $download_url .= ".zip";
    }

    $zip_file = WP_PLUGIN_DIR."/downloadfile.zip";


    //Download file from url and save to zip file
    $response = wp_remote_get($download_url);
    file_put_contents($zip_file, wp_remote_retrieve_body($response));

    $zip = new ZipArchive;
    //$extractPath = $zip_file;

    if($zip->open($zip_file) != "true"){
      echo "Error :- Unable to open the Zip File";
    }else{
      rename(WP_PLUGIN_DIR."/".$folder_name,WP_PLUGIN_DIR."/".$folder_name.'-old');
      $zip->extractTo(WP_PLUGIN_DIR.'/');
      $zip->close();
      $removefolder = WP_PLUGIN_DIR."/".$folder_name.'-old';
      
      //call our function
      autoupdaterestore_remove_directory($removefolder);
    }

    unlink(WP_PLUGIN_DIR."/downloadfile.zip");
  }
