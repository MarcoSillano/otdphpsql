# ODTPHPSQL

This class is a simple "ODT report system" for mySQL, using '.odt' templates and producing reports including text, tables, images in almost any format (odt, doc, pdf, txt etc..). All Reports can be defined or updated at run-time, using only a $templateArray (stored in a database table) as descriptor of all Fields, Blocks, Images and queries.

## How to create your template

The Report template file is build using OpenOffice, '.odt', and can contains Field, Block, Image tags that are repaced from the results of SQL queries to a mySQL database.

### Fields

If you want to map a single field you can just use `#NAME#` but you could use anything you like since it's just a search & replace

*  All field-names must be unique and are not case sensitive 
*  Field-names like `#img_xxxx#` are reserved for images 
*  Fields can be palced in template body, header, footer 

### Blocks

Place nested blocks in Template (only body) using [start blockname] and [end blockname] alone in a template text row: 

    [start blockname]
       your content,#fields#, image...
    [start somenestedblock]
       more content, #fields#, image...
    [end somenestedblock]
       ...
    [end blockname]

*  blockname should be unique 
*  blockname has to match \w+ reg ex
*  fields mapped in block has to be unique 

### Images

Put one or more dummy images in template and near the image, in same block, place a field having a name starting by "img_" (e.g. `#img_user#` or `#img_001"`).

Limits: 

*  only one image per template, per block or per nested block 
*  the image size is fixed in template 

## How to update the template

Using only a `$templateArray` (stored in a database table) as descriptor of all Fields, Blocks, Images and queries, like: 

     $templateArray = array(
          array("reportId"=>'template4', "block"=>'', "query"=>$query0, "parent"=>''), 
              //   block=='': for fields in template [0..n]
          array("reportId"=>'template4', "block"=>'blockname', "query"=>$query1, "parent"=>''), 
              //   parent =='': for blocks (1 per block) 
          array("reportId"=>'template4', "block"=>'somenestedblock', "query"=>$query2, "parent"=>'blockname') 
              //   for nested blocks (any deep, 1 per nested block)	
          ); 
                            

### Fields

1. field/values can be pre-defined (like 'today', 'user', etc..) see constructor `Odtphpsql()` 
2. field/values can be assigned as Strings: see `assign($field,$value)` 
3. field/values can be assigned as Array: see `assignArray($fields)`
4. field/values can be read at run-time from a DB, using a query: see `assingFieldsSQL($query)` 
5. field/values can be defined using one or more records in `$templateArray` 

Any query must return a table having some column names equals to field-names in template, only first record is used. 

### Blocks and nested blocks

1. Blocks can be defined in code: see assignBlock() and `assignNestedBlock()` 
2. Blocks can be defined using one record per block in `$templateArray` 


Any query must return a table having some column names equals to all field-names in Block.

The number of found records cantrols the Blocks duplication.

### Images

Image Fields must have as value the path (relative or absolute) to image file to be used. The dummy images will be replaced in the report.

* Use only '/' as path separator also in win. 
* It allows some file type change: tested '.jpg' in template and '.png' as replacement. 
* In case of error, the filed will be replaced by a message, else the field `#img_xxx#` is deleted.
 
### Queries

* Any query can have `#field#` macros, replaced by current value (in `$Odtphp->assigned_field`). This is valid also for `getArraySQL()`. 
* In nested Blocks queries are allowed fields getting value from parent result, like `#2#`(2 = field position in row, starting from 0)
 
## How to get the report

The resulting report (.odt) can be saved as file in server and/or sended to client.

Using `downloadODT()` the report can be open in client OpenOffice, so the user can save it in any supported format ("Save as") or can export it in PDF.

## How to use

    $odtsql = new Odtphpsql($templateFile);       //  constructor
    $odtsql->assign("title","My SQL Report");     //  basic field mapping	   
    $templateArray = $odtsql->getArraySQL($query_for_template);	 //  gets the descriptors for this template
    $odtsql->assignAllSQL($templateArray );       //  sql fields and blocks definitions via templateArray	 
    $odtsql->saveODT($outputFile);                //  optional save
    $odtsql->downloadODT($name);                  //  and/or send to client  

    $reportDescription = $odtsql->replaceMacros('My SQL Report for #target# (#today# #now#)');

## Extras

* Anonymizer: The author and data are raised to "odtphp" and the actual data. (Take care to delete all old versions from the template file.) 
* Template analyse functions: see `getFieldNames(), getBlockNames(), getNestedBlockNames()`. 
* Utility: string macro-replacement using stored field/values: see `replaceMacros($string)`. 
* Utility: `getArraySQL($query)` updates the query (if applicable) and returns the query result as rows a array of rows 

## Final notes

* `Odtphpsql->mountNanes` and `Odtphp->filter()` are language specific (italian): not found more portable solution :(.
* Uses `odtphp.php`, a port of djpate phpdocx (nice work!: see djpate/docxgen ) to odt, and `pclzip.lib.php`. 
* License GPL 
