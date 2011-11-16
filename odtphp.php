<?php
    /**
	*  This is a port of phpdocx (http://djpate.com/) to OpenOffice odt.
	*  
	*  ver 1-01 16/11/2011 original write (m.s.) 
	*  
	*  license GPL 
	*  author Marco Sillano  (odtphpsql@gmail.com)
	*/		

	require_once dirname(__FILE__)."/lib/pclzip.lib.php";

	define ("__thisversion__", "1-01");
	
	class Odtphp{

		private $template;
		private $content;
		private $style;	   
		private $manifest;
		private $tmpDir = "/tmp/odtphp/parts"; // must be writable
		private $assigned_field = array();
		private $assigned_block = array();
		private $assigned_nested_block = array();
		private $block_content = array();
		private $block_count = array();
		private $images = array();
		private $nested_block_count = array();
		private $fieldNames = array();
		private $blockNames  = array();
		private $nestedBlockNames  = array();
		private $processed = FALSE;	
		private $hasimages = FALSE;
		
	/**
	*  Reads the template and does the initial stuff
	*/	
public function Odtphp($template){

			if(file_exists($template)){
				$this->template = $template;
			} else {
				throw new Exception("The template ".$template." was not found !");
			}	   
		    $this->extract(); 				
		}	 
		
	/**
	* Access to fields names present in template
	*/		 
public function getFieldNames()	{
	   
		return $this->fieldNames;
		}	 
		
	/**
	* Access to Block names present in template
	*/		 
public function getBlockNames()	{
	   
		return $this->blockNames;
		}
			
	/**
	* Access to nested Block names present in template
	*/		 		 
public function getNestedBlockNames(){	
   
		return $this->nestedBlockNames;
		}	 
	
	/**
	* basic field assign using strings
	*/
public function assign($field,$value){	   
		
    	$this->assigned_field[$field] = $this->filter($value);
		}	
			 
		/**
    	* basic field assign using array
		* $fields as from mySQL: 
		*      $fields = mysql_fetch_assoc($res)
		*/		  
public function assignArray($fields){	   
			foreach($fields as $field => $value){
    		      $this->assigned_field[$field] = $this->filter($value); 
			}
		}
		
		/**
    	* basic Block assign: one Block per data row
		*	$values as from mySQL:
		*	    while ( $row = mysql_fetch_assoc($res)) {
		*		    array_push($values, $row);	} 
		*
		* see Odtphpsql->getArraySQL($query) 
		*/
public function assignBlock($blockname,$values){  
		
			$this->assigned_block[$blockname] = $values;
		}
		 
		/**
    	* basic nested Block assign:    
		* position on the tree done using a parent index array
		*	$values: see  assignBlock()
		*	$parent like: array("members"=>1,"pets"=>1))  (starting from 1)
		*/		
public function assignNestedBlock($blockname,$values,$parent){ 
		
			array_push($this->assigned_nested_block,array("block"=>$blockname,"values"=>$values,"parent"=>$parent));
		    }
		
		/**
		*  Replaces '#field#' using field/value in $assigned_field.
		*  No image prossing: image fields (#img_xx#) are stripped out
		*  see: replaceFields()
		*/  		
public function replaceMacros($string){	

	   $tmp = $string;
	   foreach($this->assigned_field as $field => $value){  
		      if ( stripos($field,'img_') === 0) {	
					 $tmp = str_ireplace('#'.$field.'#','',$tmp);	
					}
				  else {
					 $tmp = str_ireplace('#'.$field.'#',$value,$tmp);	
				  }
	    }
	 return $tmp;
	}
		
	/**
	* Does all replacements ad saves resulting  file in $outputFile 
	* $outputFile: complete path, '.odt' extension 
	* Re-callable many times
	*/
public function saveODT($outputFile){

     	if (!$this->processed) {				  //  only first time
			// cuts blocks in content
			$this->content = $this->parseBlocks($this->content);
			// for images: puts a place marker in manifest
		    if ($this->hasimages) {  
			    $pos = strpos($this->manifest,'<manifest:file-entry manifest:media-type="image/');
			 	$this->manifest = substr($this->manifest,0,$pos -1)."<!-- images -->\n". substr($this->manifest,$pos);				
			 }				   
            // builds root blocks			 
			if(count($this->assigned_block)>0){
				foreach($this->assigned_block as $block => $values){				
					foreach($values as $value){
						$this->addBlock($block,$value);
					}
				}
			}
            // builds all nested  blocks			 		
			if(count($this->assigned_nested_block)>0){
				foreach($this->assigned_nested_block as $array){
					$this->addNestedBlock($array['block'],$array['values'],$array['parent']);
				}
			}
// replace fields in all content and does template (not in block) image processing				
    		$this->content = $this->repaceFields($this->content, $this->assigned_field);	 // processes images
// replace fields for headers and footers			
			$this->style = $this->replaceMacros($this->style);  // dont processes images
			}	// end if not processed  
//  zips the outputFile			
		$this->compact($outputFile);
		$this->processed = TRUE;
		}					
				   
	/**
     * sends resulting  file to client, as response page 
	 * $name = file name, sended to client (no final '.odt')   
	 *  If $name is null, it uses a random name.
	 */
public function downloadODT($name = null){
			
	        $tmp_filename = $this->tmpDir."/../".uniqid('', true).".odt";
			if(is_null($name)){
				$name = basename($tmp_filename);
			}	  
			$this->saveODT($tmp_filename);
			$this->downloadFile($tmp_filename, $name.".odt");
	}			  			 
		 
// ==============================   private functions
	 	 /*
		 * template processing stat step:
		 * Reads and unzip template file.
		 * Gets fields, blocks, nestedBlocks names.
		 */
       private function extract(){		
	   
			if(file_exists($this->tmpDir) && is_dir($this->tmpDir)){
		//cleanup of the tmp dir													  
				$this->rrmdir(realpath($this->tmpDir.'/../'));
			}
		 // extracts dot template in tmpDir
			@mkdir($this->tmpDir);		
			$archive = new PclZip($this->template);
			$archive->extract(PCLZIP_OPT_PATH, $this->tmpDir);	
        // get the contents 
			$this->content = file_get_contents($this->tmpDir."/content.xml");
			$this->style = file_get_contents($this->tmpDir."/styles.xml");	   
			$this->manifest = file_get_contents($this->tmpDir."/META-INF/manifest.xml");	
			$this->hasimages  = !(strpos($this->manifest,'media-type="image') === false); 			
        // cleanup before processing:  
		    $this->pre_clean();		
		// extra template analysis
        // get Fields names												 								  
			preg_match_all('/#(\w+)#/',$this->content,$fieldsC);
			preg_match_all('/#(\w+)#/',$this->style,$fieldsS);		
            $this->fieldNames =array_values(array_unique(array_merge($fieldsC[1], $fieldsS[1])));
        // get Blocks names
	  		preg_match_all('/\[start (\w+)\].*?\[end \1\]/',$this->content,$fields);
            $this->blockNames = array_values(array_unique($fields[1]));	
        // get NestedBlocks names			
	  		preg_match_all('/\[start (\w+)\]/',$this->content,$fields);
    		$this->nestedBlockNames = array_values(array_unique(array_diff($fields[1],$this->blockNames)));   	
			$this->processed = FALSE;		   
			
			}
	
	 	 /*
		 * template processing final step:
		 * zips new file in  $output.
		 */
        private function compact($output){
 
	        if (!$this->processed) {
            // cleanup after processing:  
	    	    $this->post_clean();			
	        // copy	data
				file_put_contents($this->tmpDir."/content.xml",$this->content);
				file_put_contents($this->tmpDir."/styles.xml",$this->style);	
			    if ($this->hasimages) {  
				    file_put_contents($this->tmpDir."/META-INF/manifest.xml",$this->manifest);	
				    }
				}  // end if not processed 
			// zips
			$archive = new PclZip($output);
			$archive->create($this->tmpDir,PCLZIP_OPT_REMOVE_PATH,$this->tmpDir);	
		
	   }			
			  
		/*
		*  Replaces '#field#' using $array (couples field/value) 
		*  This does image prossing: image fields (#img_xx#) are stripped out but a new image replaces the dummy image in template.
		*  see: replaceMacro()
		*/  
	  private function repaceFields($template, $array) {	
	  	  
	   foreach($array as $id => $val){  
	           if ( stripos($id,'img_') === 0)	{ 
			          if ($this->hasimages)	{
					     $template = $this->immageProcessing( $id, $val, $template);	
						 }  
				      else {
                         $template = str_ireplace('#'.$label.'#','ERROR: not found images in Template!', $template); 
						 }
					 }
			     else {
					  $template = str_ireplace('#'.$id.'#',$this->filter($val),$template);	
				 }
		}
      return $template;
	  
	  }

	  /*
	  *	 All image stuff. Not recursive, only one Image per block
	  */
	  private function immageProcessing($label, $image, $template) {
	    
	  if ($frompath = realpath($image)) {
			  // copy file -> update manifest	
			  $fullpath =  "Pictures/".uniqid(true).strstr($image, '.'); 
			  $topath = $this->tmpDir."/".$fullpath;
			  copy($frompath, $topath);  
			  // update manifest
			  $this->manifest = str_replace('<!-- images -->','<manifest:file-entry manifest:media-type="image/jpeg" manifest:full-path="'.$fullpath.'"/>'."\n<!-- images -->",$this->manifest);
			  // update file  
	          $template = preg_replace('/draw:image xlink:href="\S*"/','draw:image xlink:href="'.$fullpath.'"',$template);
			  // destroy field
			  $template = str_ireplace('#'.$label.'#','', $template); 	 
		      return $template;
		  }	else  {
              $template = str_ireplace('#'.$label.'#','ERROR: file ('.$image.') not found!', $template); 
              return $template;
		  }
		  
	}			
		
       /*
	   * Function download to client
	   */ 	
       private function downloadFile($filepath, $name) {
	   
            header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename='.$name);
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
			header('Content-Length: ' . filesize($filepath));
			ob_clean();	
			flush();
			readfile($filepath);
			   	
        }			
 
		/*
		* Strips and stores all Blocks from template content.
		*/		
		private function parseBlocks($txt){		   
		
			$matches = array();
			$ret = $txt;
			preg_match_all('/\[start (\w+)\].*?\[end \1\]/s',$txt,$matches);
    		if(count($matches[1])>0){
				foreach($matches[1] as $block){
					$ret = $this->parseBlock($block,$ret);
				}
			}		
			return $ret;		
			
		}				  
		
		/*
		 * recursive step for  $parseBlocks
		 */
		private function parseBlock($name,$txt){
		
			// we strip the block markup 
			$previous_pos = $this->getPreviousPosOf("start ".$name,"<text:p ",$txt);
			$end_pos = $this->getNextPosOf("start ".$name,":p>",$txt) + 3;			
			$txt = str_replace(substr($txt,$previous_pos,$end_pos-$previous_pos),"<!-- start ".$name." -->",$txt);			
			$previous_pos = $this->getPreviousPosOf("end ".$name,"<text:p ",$txt);
			$end_pos = $this->getNextPosOf("end ".$name,":p>",$txt) + 3;			
			$txt = str_replace(substr($txt,$previous_pos,$end_pos-$previous_pos),"<!-- end ".$name." -->",$txt);			
			// we save the template content for the block 
			$block = preg_match("`<!-- start ".$name." -->(.*)<!-- end ".$name." -->`",$txt,$matches);			
			if(array_key_exists(1,$matches) > 0){
				$this->block_content[$name] = $this->parseBlocks($matches[1]);
			}			
			// we remove the template content from the doc 
			$txt = preg_replace('`<!-- start '.$name.' -->(.*)<!-- end '.$name.' -->`','<!-- start '.$name.' --><!-- end '.$name.' -->',$txt);
			return $txt;	
			
		}  
		
		  /*
		  *replaces top level Blocks from assigned_block
		  */
		private function addBlock($blockname,$values){			
		
			$block = $this->block_content[$blockname];			
			if(array_key_exists($blockname,$this->block_count)){
					$this->block_count[$blockname] = $this->block_count[$blockname] + 1;
				} else {
					$this->block_count[$blockname] = 1;
				}
			$block = $this->repaceFields($block, $values);			
			$this->content = str_replace("<!-- end ".$blockname." -->","<!-- block_".$blockname."_".$this->block_count[$blockname]." -->".$block."<!-- end_block_".$blockname."_".$this->block_count[$blockname]." --><!-- end ".$blockname." -->",$this->content);			

		}	
		
		/*  
		 * low-level: replaces one nested Block from $assigned_nested_block .	 
		 *   $values is an array of arrays fields/values: one array for block
		 *	 $parent tree position like: array("members"=>1,"pets"=>1))  (starting from 1)
		 */
         private function addNestedBlock($blockname,$values,$parent){

			if(is_array($parent) && count($parent)>0){			
				$block = "";
				$regex = '`(.*)`';				
				$link_nested_count = array();				
				foreach($parent as $id => $node){				
					if($regex == "`(.*)`"){
						$regex = str_replace("(.*)","<!-- block_".$id."_".$node." -->(.*)<!-- end_block_".$id."_".$node." -->",$regex);
					} else {
						$regex = str_replace("(.*)",".*<!-- block_".$id."_".$node." -->(.*)<!-- end_block_".$id."_".$node." -->.*",$regex);
					}						
				    array_push($link_nested_count,$id.$node);
				}				
				$idnested = implode("_",$link_nested_count)."_".$blockname;				
				if(array_key_exists($idnested,$this->nested_block_count)){
//					$current_index = $this->nested_block_count[$idnested] + 1;
					$this->nested_block_count[$idnested]++;
				} else {
					$this->nested_block_count[$idnested] = 1;
//					$current_index = 1;
				}			
				$block_content = $this->block_content[$blockname];
				$blockIndex =1;
				foreach($values as $row){
					$current_block = $block_content; 
			        $current_block = $this->repaceFields($current_block, $row);			
					$block .= "<!-- block_".$blockname."_".$blockIndex." -->".$current_block."<!-- end_block_".$blockname."_".$blockIndex." -->";
					$blockIndex ++;
				}				
			 preg_match($regex,$this->content,$matches);					
			 $new = str_replace("<!-- end ".$blockname." -->",$block."<!-- end ".$blockname." -->",$matches[1]);  
			 $regex2 = str_replace('(.*)',').*(',$regex);
			 $regex2 = str_replace('`<','`(<',$regex2);
			 $regex2 = str_replace('>`','>)`',$regex2);
    		 $this->content = preg_replace($regex2,'$1'.$new.'$2',$this->content);			
			} else {				
				throw new Exception("Parent list for $blockname cannot be empty");
			}			
			
		}	  
		
		/*
		* util: to handle special chars in odt (XML) file
		*/
		private function filter($value){  	 				  
	
		    $ret = $value;
			$ret = str_replace("&","&amp;",$ret);
			$ret = str_replace("<","&lt;",$ret);   
	// lang specific (italian). 	
			$ret = str_replace("à","&#224;",$ret);
			$ret = str_replace("è","&#232;",$ret);
			$ret = str_replace("é","&#233;",$ret);
			$ret = str_replace("ò","&#242;",$ret);
			$ret = str_replace("ì","&#236;",$ret);
			$ret = str_replace("ù","&#249;",$ret);
			$ret = str_replace("€","&#8364;",$ret);
			return $ret;  				
		}	  																							
														  
		/*
		* cleanup before template processing
		*/
		private function pre_clean(){	
						  
		// Anonymizer
			$meta = file_get_contents($this->tmpDir."/meta.xml");	   
		 //  <dc:title>Idoneità - Allegato A</dc:title>
         //  <meta:initial-creator>Marco Sillano</meta:initial-creator>
         //  <meta:creation-date>2011-11-05T14:05:00</meta:creation-date>
         //  <dc:date>2011-11-14T19:30:07.18</dc:date>
         //  <dc:creator>Marco Sillano</dc:creator>
			$meta = preg_replace('`<meta:initial-creator>.*</meta:initial-creator>`','<meta:initial-creator>Odtphp ver.'. __thisversion__.'</meta:initial-creator>',$meta);
			$meta = preg_replace('`<meta:creation-date>.*</meta:creation-date>`','<meta:creation-date>'.date('Y-m-d\TH:i:s.00').'</meta:creation-date>',$meta);
			$meta = preg_replace('`<dc:creator>.*</dc:creator>`','<meta:initial-creator>Odtphp ver.'. __thisversion__.'</meta:initial-creator>',$meta);
			$meta = preg_replace('`<dc:date>.*</dc:date>`','<dc:date>'.date('Y-m-d\TH:i:s.00').'</dc:date>',$meta);
			file_put_contents($this->tmpDir."/meta.xml",$meta);
		 //
		 
		}

		/*
		* cleanup after template processing
		*/
		private function post_clean(){
	        // cleanup after processing: eliminates empty blocks									 
			$this->content = preg_replace('`<!-- start (\w+) -->\s*<!-- end \1 -->`','',$this->content);
		}

		/*
		*  util: recursive delete dir
		*/
		private function rrmdir($dir) {	 		
		   if (is_dir($dir)) {
			 $objects = scandir($dir);
			 foreach ($objects as $object) {
			   if ($object != "." && $object != "..") {
				 if (filetype($dir."/".$object) == "dir") $this->rrmdir($dir."/".$object); else unlink($dir."/".$object);
			   }
			 }
	       rmdir($dir);
		   }
		}		
		
		/*
		* util: used by  parseBlock
		*/
		private function getNextPosOf($start_string,$needle,$txt){
	
			$current_pos = strpos($txt,$start_string);
			$len = strlen($needle);
			$not_found = true;
			while($not_found && $current_pos <= strlen($this->content)){
				if(substr($txt,$current_pos,$len) == $needle){
					return $current_pos;
				} else {
					$current_pos = $current_pos + 1;
				}
			}			
			return 0;			
		}		
		
		/*
		* util: used by  parseBlock
		*/
		private function getPreviousPosOf($start_string,$needle,$txt){
	
			$current_pos = strpos($txt,$start_string);
			$len = strlen($needle);
			$not_found = true;
			while($not_found && $current_pos >= 0){
				if(substr($txt,$current_pos,$len) == $needle){
					return $current_pos;
				} else {
					$current_pos = $current_pos - 1;
				}
			}			
			return 0;			
		}			 		
} 

?>


