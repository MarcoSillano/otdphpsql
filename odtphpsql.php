<?php

 include(dirname(__FILE__)."/odtphp.php");	

/**
* This class defines a simple "ODT report system" for mySQL, using '.odt' templates and
*   producing reports including text, tables, images in almost any format (odt, doc, pdf, txt etc..).
* 
* The Report template file (build using OpenOffice, '.odt') contains Field, Block, Image tags
* that are repaced from the results of SQL queries to a mySQL database.
*
* All Reports can be defined or updated at run-time, using only a $templateArray
* (stored in a database table) as descriptor of all Fields, Blocks, Images and queries:	  
*
* $templateArray = array(array("reportId"=>'template4', "block"=>'', "query"=>$query0, "parent"=>''),		    // block=='': for fields in template [0..n]
*                        array("reportId"=>'template4', "block"=>'base', "query"=>$query1, "parent"=>''),	    // parente=='': for blocks (1 per block)
*			             array("reportId"=>'template4', "block"=>'more', "query"=>$query2, "parent"=>'base') ); // for nested blocks (any deep, 1 per nested block)	
*	
* note: all field-names must be unique and are not case sensitive. 
* note: block names must match '\w+' reg ex.
* note: reportId can be the template name and/or the resulting report file name	
* note: any query must return a table having column names equals to field-names in template or block (#field-name#),
*      the number of found records cantrols the Blocks numbers.
*  
* Replacement in Template (header, body, footer) using '#field#' :
*      field/values can be pre-defined (like today, user, etc..) see constructor Odtphpsql()
*      field/values can be assigned as Strings: see assign($field,$value)
*      field/values can be assigned as Array: see assignArray($fields)
*      field/values can be read at run-time from a DB, using a Query: see assingFieldsSQL($query)
*      field/values	can be defined using one or more records in $templateArray.
* 	
* Recursive block replacement in Template (only body) using '[start blockname]'...'[end blockname]' alone in a template text row:
*	   Both root Blocks and nested Blocks can be defined in code: see assignBlock() and assignNestedBlock()
*      All Blocks can be defined using one or more records in $templateArray.	 
*
* note: all queries can be updated at runtime:
*      Any query can have #field# macros, replaced by current value (in $Odtphp->assigned_field), valid also for getArraySQL().
*      In nested Blocks queries are allowed fields getting value from parent result, like #2# (2 = field position in row array, starting from 0).
*            This take precendence over a field having same name (#2#) in $Odtphp->assigned_field.
* 
* Image replacement:
*      Put one or more dummy images in template and near the image, in same block, place a field having a name starting
*      by "img_" (e.g. #img_user# or #img_001"). The value must be the path (relative or absolute) to image file to be used. 
*      The images will be replaced in the report.
*      limits: only one image per template, per block or per nested block;
*              the image size is fixed in template;
*              It allows some file type change: tested '.jpg' in template and '.png' as replacement.
*  note: use only '/' as path separator also in win. 
*  note: the field names like #img_xxxx# are reserved for images.
* 
* Report: 
*  The resulting report (.odt) can be saved as file in server and/or sended to client.
*  note:  using downloadODT() the report can be open in client OpenOffice, so the user can save it
*      in any supported format ("Save as") or can export it in PDF.
*
* Extras:
*     Anonymizer: The author and data are raised to "odtphp" and the actual data. (Take care to delete all old versions from
*          template file.)
*     Template analyse functions: see  getFieldNames(),  getBlockNames(), getNestedBlockNames().
*     utility: string macro-replacement using stored field/values: see replaceMacros($string).	
*     utility: getArraySQL($query) updates the query (if applicable) and returns the query result as rows array. 							 
*
* Use:
*	$odtsql = new Odtphpsql($template);							 //  constructor
*	$odtsql->assign("title","My SQL Report");                    //  basic field mapping	   
*   $templateArray = $odtsql->getArraySQL($query_for_template);	 //  gets the descriptors for this template
*	$odtsql->assignAllSQL($templateArray );					     //  sql fields and blocks definitions via $templateArray	 
*   $odtsql->saveODT($outputFile);							     //  optional save
*	$odtsql->downloadODT($name);							     //  and/or send to client  
*   $reportDescription = $odtsql->replaceMacros('My SQL Report for #target# (#today# #now#)');
* 
* note:  Odtphpsql>mountNanes and Odtphp->filter() are language specific (italian).	
* -----------------------------		  
*  dependecies:
*        odtphp.php
*        lib/pclzip.lib.php 				   
*
*  ver 1-01 16/11/2011 original write (m.s.) 
*  
*  license GPL 
*  author Marco Sillano  (odtphpsql@gmail.com)
*/		


class Odtphpsql extends Odtphp{

// lang specific (italian).
   public   $mountNanes = array('','Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre');

/**
* constructor
* $template: the template full path
*/

public function Odtphpsql($template){	

		$this->Odtphp($template);
// pre-defined fields, application dependent: date, version, user etc...	
//         setlocale(LC_TIME, 'it_IT');  
//         $this->assign("today", strftime("%e %B %Y", null));  // dont works as expected
        $this->assign("today",date("d")." {$this->mountNanes[date('n')]} ".date("Y")); // basic field mapping	   
        $this->assign("date",  date("d/N/Y")); // basic field mapping	   
        $this->assign("now",   date("G:i"));   // basic field mapping
		  
 }		
 
 /*
 *	low-level DB read using SQL.
 *  note: the DB must be connected. 
 *  note: in queries are allowed fields (#field#) replaced by actual values in $this->assigned_field
 */	 
public function getArraySQL($query){	   
//      the function sql(query, error) is here for compability with AppGini environment.
//      if(!function_exists('sql')){  
//			die('error: the function sql(query, error) must be defined to use getArraySQL()!');
//		    exit;  
//		}			
//	   $e=array('silentErrors' => false);  
//  	'silentErrors': If true, errors will be returned in $e['error'] rather than displaying them on screen and exiting.
	   $q = $this->replaceMacros($query);
//       $r=sql($q, $e);			   // use sql()  or  mysql_query()
	   $r = mysql_query($q);
	   $arrayData= array();
       while ( $sub = mysql_fetch_array($r)) {	  
					array_push($arrayData, $sub);  
 					}				   
	   return $arrayData ;	   
 }

 
	/**
	*	Simple assign Fields using the first record get by the query.
	*/		
public function assingFieldsSQL($query){	
	   $blockData= $this->getArraySQL($query);
 	   $this->assignArray($blockData[0]);
 }	

	/**
	*	Simple assign Block	(not nested) using all records get by the query. 
	*   returns a data array, as required by assingBlockSQLrecursive.
	*/		
			
public function assingBlockSQL($blockName, $query){	
	   $blockData= $this->getArraySQL($query);
 	   $this->assignBlock($blockName,$blockData);
	   return  $blockData;   		
 }	
	 	 
 
/**
* Using $templateArray as descriptor for fields, Blocks and queries:
*/ 					 
  
public function assignAllSQL($templateArray ){   

        foreach($templateArray as $block){	
	      if ($block['block'] == '') {	 				  // fields
		     $this->assingFieldsSQL($block['query']);
		  }
		  else
	      if ($block['parent'] == '') {					  // top blocks
          	$this->assingBlockSQLrecursive( $block['block'], $block['query'], $templateArray );	
		  }
	   }	        
 }		
 	   
// ================== privates	
//	 used by assignAllSQL
//	 for blocks top level
  
private function assingBlockSQLrecursive( $blockName, $query, $templateArray){	

	   $blockData= $this->assingBlockSQL( $blockName, $query);
	   foreach($templateArray as $block){	
	      if ($block['parent'] == $blockName) {	
			 $this->assignNestedBlockSQLrecursive( $block['block'], $block['query'], $templateArray, $blockData, $blockName, array() );
	        }
	   }   
 }	  
	
//	 used by assignAllSQL
//	 any deep
  
private function assignNestedBlockSQLrecursive($nestedBlockName, $query, $templateArray, $parentData, $parentName, $tree){	

       preg_match ( '/#(\d+)#/', $query, $found);
	   $pos = $found[1];	
	   $i = 1;	// parent index	
	     
	   foreach($parentData as $row){	 
	      $q = str_replace("#$pos#", $row[$pos], $query);  	  
		  $rTree = $tree;
		  $rTree[$parentName]=$i; 	 // parent and 	index appended to $tree
		  $subData= $this->getArraySQL($q); 
		  $this->assignNestedBlock($nestedBlockName,$subData,$rTree);	  
    	  foreach($templateArray as $block){	
	         if ($block['parent'] == $nestedBlockName) {  
	              $this->assignNestedBlockSQLrecursive( $block['block'], $block['query'], $templateArray, $subData, $nestedBlockName, $rTree);	   		    
			       }
	      }	// end foreach	 
		  $i++;
	   } // end foreach		 	 	
    }			  
}
 
?>
  
