<?php

/* (c) Dúl Zoltán 2014, 2015, 2016 */
/* SVG Analyzer plugin */

include_once("analyzer_include_mySQL2018.php");

$this_file = basename(__FILE__);

//osztályok

class Lekeres {

	public $sorsz = 0;
	public $lista;
	public $values;
	public $printelni = ""; // ebbe megy az SVG kódja
	public $informaciok = ""; // ez az információk stringje
	private $faj = array();
	private $faj_mid2lil = array();
	private $kiiras_beolv;
	private $kiir_fajl;
	private $kiiras = false;
	private $species = array();
	public $species_number = 0;
	private $get_values = array();
	private $fajok = array();
	private $counter = array();
	private $mappings = array();
	private $unip2groupID = array();
	private $groupID2unip = array();
	private $groupID2spec = array();
	private $container = array();
	private $value_tomb = array();
	private $protein_list = array();
	private $protein_list2 = array();
	private $folders;
	private $arrSizeControl = array();
	public $containertable = array();
	public $printertable = "";

	public function __construct($files, $folders, $faj, $given_values, $gos) {

		// mysql connect
		self::mysql_conn();

		// handle given values
		self::get_values($given_values, $faj);

		// $faj & $folders handle
		$this->faj = $faj;
		$this->folders = $folders;

		// output file
		$this->kiir_fajl = $files["output"];

		foreach($faj as $key => $arri) {

			if(! in_array(strtoupper($key), $this->species["lil"]) ) continue;
			$this->protein_list[ $arri["lil"] ] = array();
			$this->protein_list2[ $arri["lil"] ] = array();
			$this->mappings[ $arri["mid"] ] = array();
			$this->faj_mid2lil[ $arri["mid"] ] = $arri["lil"];
		}

		// MySQL DB process
		$this->lista = self::db_processor($given_values["go"]);

		$this->values = self::analyzer($faj);

		return true;
	}

	private function mysql_conn (){

		$config['user'] = 'root';
		$config['password'] = 'zolis';
		$config['db'] = 'orthology';
		$config['host'] = 'localhost';
		$config['port'] = 3306;
		$config['tables'] = array("orthology_databases");

		$this->MySQLiLink = mysqli_init();

		if (!mysqli_real_connect( $this->MySQLiLink, $config['host'], $config['user'], $config['password'], $config['db'], $config['port'])) {
		    die('Connect Error (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());
		}

		return true;
	}

	private function get_values($given_values, $faj) {

		$this->get_values["mit"] = $given_values["mit"];
		$this->get_values["species"] = $given_values["spec"];
		$this->get_values["inside"] = $given_values["ins"];
		$this->get_values["first"] = $given_values["first"];
		$this->get_values["go"] = $given_values["go"];
		$this->get_values["sizemanual"] = $given_values["sizemanual"];

		if (count($this->get_values["species"] ) == $given_values["num"] ) {

			// case where "number of species" given and "number" given is identical
			// we check back, that each species must be in our library if not given_numbers species is getting selected.

			$check_back = false;

			foreach ($this->get_values["species"] as $key => $this_spec) {
				if(array_key_exists(strtolower($this_spec), $faj)) $this->species[] =  strtolower($this_spec);
				else $check_back = true;
			}

			if($check_back) {
				$random_species = array_rand($faj, $given_values["num"]);
				$this->species = $random_species;
			}

		}
		else {

			// in case where something is not identical in numbers. We selec 5 random species and choose 5-set venn diagram

			$random_species = array_rand($faj, 5);
			$this->species = $random_species;

		}

		$lils = array();
		$mids = array();
		$count = 0;

		sort($this->species);

		foreach ($this->species as $key => $lil) {

			$lils[] = strtoupper($lil);
			$mids[$faj[$lil]["mid"]] = $faj[$lil]["mid"];

			// counter beállítása

			$this->counter[$faj[$lil]["mid"]] = 0;

			$count++;
		}

		$this->species = array("lil" => $lils, "mid" => $mids);
		$this->species_number = $count;

		return true;
	}

	private function db_processor($go) {

		$db_processor_time = microtime(true);
		
		/*
		$thisSQL = "SELECT `faj1`, `uniprot1`, `faj2`, `uniprot2`, `db1`, `db2`, `db3`, `db4`, `db5`, `db6` 
					FROM `orthology_databases` AS `orth`
					INNER JOIN `geneontology` ON (orth.uniprot1 = geneontology.uniprot)
					WHERE geneontology.super_acc = '" . $go . "'";
		*/
		// /*

		$thisSQL = "SELECT `faj1`, `uniprot1`, `db1` 
					FROM `orthology_databases` AS `orth`
					INNER JOIN `geneontology` ON (orth.uniprot1 = geneontology.uniprot)
					WHERE geneontology.super_acc = '" . $go . "' AND (orth.db1 != '')
					GROUP BY(orth.db1)";			

		$result = mysqli_query( $this->MySQLiLink, $thisSQL );
		if(mysqli_errno($this->MySQLiLink)) print mysqli_error($this->MySQLiLink);

		
		
		if($go == "GO:0008361") {

			if($this->get_values["sizemanual"]) {

				$file = "output/ALL_ortholog_dbs_merged_added.csv";
				$this->arrSizeControl = $this->leker_lista($file);

			}

			while ($row = mysqli_fetch_array($result) ) $this->arrSizeControl[ $row['db1'] ] = $row['db1'];

			$groupQuery = "'" . implode("','", $this->arrSizeControl) . "'";

		}

		else {

			$groupQuery = "";
			while ($row = mysqli_fetch_array($result) ) $groupQuery .= "'" . $row['db1'] . "',";
			$groupQuery = substr($groupQuery, 0, -1);

		}


		// SECOND


		$thisSQL = "SELECT `faj1`, `uniprot1`, `db1` 
					FROM `orthology_databases` AS `orth`
					INNER JOIN `geneontology` ON (orth.uniprot1 = geneontology.uniprot)
					WHERE geneontology.super_acc = '" . $go . "' AND (orth.db1 != '')
					GROUP BY(orth.uniprot1)";		

		$result = mysqli_query( $this->MySQLiLink, $thisSQL );
		if(mysqli_errno($this->MySQLiLink)) print mysqli_error($this->MySQLiLink);

		while ($row = mysqli_fetch_array($result) ) {

			$this->protein_list[ $this->faj_mid2lil[$row['faj1']] ][$row['uniprot1']] = $row['uniprot1'];
			//if($row['uniprot1'] == 'P11792') print "DatFuck";
		
		}

		// THIRD

		$thisSQL = "SELECT `faj1`, `uniprot1`, `faj2`, `uniprot2`, `db1`, `db2`, `db3`, `db4`, `db5`, `db6` 
					FROM `orthology_databases` AS `orth`
					WHERE orth.db1 IN (" . $groupQuery . ")";
		
		// */

		$result = mysqli_query( $this->MySQLiLink, $thisSQL );
		if(mysqli_errno($this->MySQLiLink)) print mysqli_error($this->MySQLiLink);

		$dbs = ["db1", "db2", "db3", "db4", "db5", "db6"];

		while ($row = mysqli_fetch_array($result) ) {

			if( ! array_key_exists($row['faj1'], $this->species["mid"]) ) continue;
			if( ! array_key_exists($row['faj2'], $this->species["mid"]) ) continue;

			$groupID = (( ! empty($row["db1"]) ) ? str_replace(")","",str_replace("eggNOG (","",$row["db1"])) : "" );

			 // . ( (! empty($row["db3"]) ) ? "inParanoid". "-" : "" );
			// $groupID = (( ! empty($row["db1"]) ) ? $row["db1"] . "-" : "" ) . ( (! empty($row["db2"]) ) ? $row["db2"] . "-" : "" ) . ((! empty($row["db3"] )) ? "inParanoid" . "-" : "" ) . (($row["db4"] != "") ? $row["db4"] . "-" : "" ) . (( ! empty($row["db5"]) ) ? "OrthoMCL". "-" : "" );
			
			if(empty($groupID)) continue;
			//else $groupID = substr($groupID, 0, -1);

			$this->unip2groupID[$row['uniprot1']] = $groupID;
			$this->unip2groupID[$row['uniprot2']] = $groupID;

			if(! array_key_exists($groupID, $this->groupID2unip)) $this->groupID2unip[$groupID] = array();
			if(! array_key_exists($row['uniprot1'], $this->groupID2unip[$groupID])) $this->groupID2unip[$groupID][$row['uniprot1']] = $row['faj1'];
			if(! array_key_exists($row['uniprot2'], $this->groupID2unip[$groupID])) $this->groupID2unip[$groupID][$row['uniprot2']] = $row['faj2'];

			if(! array_key_exists($groupID, $this->groupID2spec)) $this->groupID2spec[$groupID] = array();
			$this->groupID2spec[$groupID][$row['faj1']] = $row['uniprot1'];
			$this->groupID2spec[$groupID][$row['faj2']] = $row['uniprot2'];

			if(! array_key_exists($row['uniprot1'], $this->mappings[$row['faj1']])) $this->mappings[$row['faj1']][$row['uniprot1']] = array($row['faj2'] => array($row['uniprot2'] => $row['uniprot2']));
			elseif(! array_key_exists($row['faj2'], $this->mappings[$row['faj1']][$row['uniprot1']])) $this->mappings[$row['faj1']][$row['uniprot1']][$row['faj2']] = array($row['uniprot2'] => $row['uniprot2']);
			else $this->mappings[$row['faj1']][$row['uniprot1']][$row['faj2']][$row['uniprot2']] = $row['uniprot2'];

			if(! array_key_exists($row['uniprot2'], $this->mappings[$row['faj2']])) $this->mappings[$row['faj2']][$row['uniprot2']] = array($row['faj1'] => array($row['uniprot1'] => $row['uniprot1']));
			elseif(! array_key_exists($row['faj1'], $this->mappings[$row['faj2']][$row['uniprot2']])) $this->mappings[$row['faj2']][$row['uniprot2']][$row['faj1']] = array($row['uniprot1'] => $row['uniprot1']);
			else $this->mappings[$row['faj2']][$row['uniprot2']][$row['faj1']][$row['uniprot1']] = $row['uniprot1'];
			
			if(! array_key_exists($row['uniprot1'], $this->protein_list2[ $this->faj_mid2lil[$row['faj1']] ])) $this->protein_list2[ $this->faj_mid2lil[$row['faj1']] ][$row['uniprot1']] = $row['uniprot1'];	
			
			$this->counter[$row['faj1']]++;
			$this->counter[$row['faj2']]++;

			// PRINT SOME INTERRESTING PROTEINS out

			// if($row['uniprot1'] == 'P11792') print "<B>" . $row["uniprot1"] . "</B> (" . $row["faj1"] . ") --> " . $row["uniprot2"] . " (" . $row["faj2"] . ") - ".$row["db1"]."<BR>";
			// if($row['uniprot2'] == 'P11792') print $row["uniprot1"] . " (" . $row["faj1"] . ") --> <B>" . $row["uniprot2"] . "</B> (" . $row["faj2"] . ") - ".$row["db1"]."<BR>";;

		}

		//$this->protein_list = $this->protein_list2;

		$this->informaciok .= TimeEnd($db_processor_time, "DB Processor");
	}

	private function leker_lista($fajl) {

		$this->szetszed1 = "\n";
		$this->szetszed2 = "\r";

		$fajl_beolvas = fopen($fajl,"r");
		if(!$fajl_beolvas) $hiba .= "Nem tudtam beolvasni a 1. <b>" . $fajl . "</b> fájlt hozzáadásra!";

		$fajl_tartalom = fread($fajl_beolvas, filesize($fajl));
		$ujsor = explode($this->szetszed1,$fajl_tartalom);
		if(count($ujsor) < 3 ) $ujsor = explode($this->szetszed2,$fajl_tartalom);
			
		$sor = 0;
		$listerGroups = array();
			
		foreach ($ujsor as $sor_id => $sor_tartalom) {

			$sor++;
					
			if ( empty($sor_tartalom) ) continue;
			else {

				$mezo = explode(";",$sor_tartalom);

				$faj1 = $this->faj_mid2lil[ trim($mezo[0]) ];
				$unip1 = trim($mezo[1]);
				$faj2 = trim($mezo[2]);
				$unip2 = trim($mezo[3]);
				$db = trim($mezo[4]);

				$listerGroups[$db] = $db;

				if(! array_key_exists($faj1, $this->protein_list)) $this->protein_list[$faj1] = array($unip1 => $unip1);
				elseif(! array_key_exists($unip1, $this->protein_list[$faj1])) $this->protein_list[$faj1][$unip1] = $unip1;
				else continue;

			}	

		}

		return $listerGroups;
	}

	private function analyzer($faj) {

		// SVG File

			$svg_diagram = new SVG_File($this->folders, $this->species_number);

		// mid2lil & lil2mid & voltmar arrays

			$fajKeys_mid2lil = array();	
			$fajKeys_lil2mid = array();
			$voltmar = array();

			foreach ($this->species["lil"] as $key => $fajID) {
				$fajKeys_mid2lil[$faj[strtolower($fajID)]["mid"]] = $fajID;
				$fajKeys_lil2mid[$fajID] = $faj[strtolower($fajID)]["mid"];
				$voltmar[trim(strtoupper($fajID))] = array();
			}

		// create ReplacR

			// Creating a array [LETTER]:[lilfaj as value]

			$replaceR = array();
			$range = range("A","J");

			foreach ($this->species["lil"] as $k => $lilfaj) $replaceR[$range[$k]] = trim($lilfaj);

		// VENN DIAGRAM - with PERMUTATATION

			/*  EXPLANATION

				load permutation, with number & replacer info

				returned arrays: 

				$keys ->  array [SPEC_ID as key] : array [ALL_KEYS as value] - ex. [AT][0] => AT,CE,DM,DR,HS,SC,SP
				$tomb ->  array [ALL_KEYS] /ordered as longest 2 shortest/ : value: empty array[] - ex. [AT,CE,DM,DR,HS,SC,SP][]
				$sorrend -> array [ALL_KEYS as values] /ordered as longest 2 shortest/ ex. [0] => AT,CE,DM,DR,HS,SC,SP
				$transaltor ->  array [ALL_KEYS] /ordered as longest 2 shortest/ : value: translation ABC array[] - ex. [AT,CE,DM,DR,HS,SC,SP] => ABCDEFG
			
			*/

			$permutation = new VennDiagram($this->species_number, $replaceR);

			$keys = $permutation->keys;
			$tomb = $permutation->permutArray;
			$sorrend = $permutation->replacedPermutation;
			$translator = $permutation->translator;

		// create tomb_count array(); $tomb_count[KEY] = COUNT, set to zero here

			$tomb_count = array();
			foreach ($tomb as $key => $value) $tomb_count[$key] = 0;

		// mapping cycle

			$mappings = self::mappings_cycle($keys, $tomb, $fajKeys_mid2lil);
			$pair_container = $mappings["pair_container"];
			$tomb = $mappings["tomb"];

			$sor = 0;
			$tomb2 = array();
			$tomb3 = array();

		// from TOMB --> tomb2 & tomb3 filling

			/* 
				structure tomb: [faj1][faj2][unip2]
				pair containers: [faj1][faj2][unip2][unip1]
				tomb: [faj1][faj2][unip2]
			*/

			foreach ($tomb as $key => $arri) {

				if(strlen($key) == 2) {
					$tomb2[$key] = array();
					$tomb3[$key] = array();
					continue;
				}
				else $this_keys = explode(",", $key);

				if(! array_key_exists($key, $tomb2)) $tomb2[$key] = array();
				if(! array_key_exists($key, $tomb3)) $tomb3[$key] = array();
				foreach ($this_keys as $n => $this_faj) $tomb2[$key][$this_faj] = array();
				foreach ($this_keys as $n => $this_faj) $tomb3[$key][$this_faj] = array();

				foreach ($arri as $faj1 => $arri2) { 
					
					foreach ($arri2 as $faj2 => $arri3) {

						if(! in_array($faj2, $this_keys)) continue;
						
						foreach ($arri3 as $n => $unip) {

							if(! array_key_exists($unip, $tomb2[$key][$faj2])) $tomb2[$key][$faj2][$unip] = array($faj1);
							elseif(! array_key_exists($faj1, $tomb2[$key][$faj2][$unip] )) $tomb2[$key][$faj2][$unip][] = $faj1;

						}
					}
				}

				if($this->get_values["mit"] == "sum") {

					foreach ($tomb2[$key] as $this_faj => $arri) {
						
						foreach ($arri as $unip => $count) {

							if( count($count) < (count($this_keys) - 1) ) continue;
							if(! array_key_exists($unip, $tomb[$this_faj]) && $this->get_values["inside"] ) continue;
							//if($this_faj != $this->get_values["species"] && $this->spec != "ALL") continue;

							$tomb_count[$key]++;
							$tomb_count[$this_faj]++;

							if(! array_key_exists($unip, $tomb3[$key][$this_faj])) $tomb3[$key][$this_faj][$unip] = $count;

						}

					}
				}
			}

		// RealC counting perform

			$overall_list = "";

			if($this->get_values["mit"] == "realc") {

				$overall_center = 0;

				foreach ($sorrend as $numero => $this_key) {

					$this_keys = explode(",", $this_key);

					foreach ($tomb2[$this_key] as $this_faj => $arri) {
					
						foreach ($arri as $unip => $count) {

							if( count($count) < (count($this_keys) - 1) ) continue;
							if(! array_key_exists($unip, $tomb[$this_faj]) && $this->get_values["inside"]  ) continue;

							if( array_key_exists($unip, $voltmar[$this_faj]) ) continue;
							else $voltmar[$this_faj][$unip] = $unip;

							$tomb_count[$this_key]++;
							$tomb_count[$this_faj]++;

							if(! array_key_exists($unip, $tomb3[$this_key][$this_faj])) $tomb3[$this_key][$this_faj][$unip] = $count;

							if($this_key == $sorrend[0]) {

								$overall_center++;
								
								$overall_list .= "From: $this_faj: ";
								$overall_list .= $unip;
								$overall_list .= " (".implode(",", $count).") \n";

							}

							// print $unip . "<BR>"; // to retrive all of the unips that exist

						}

					}

				}
			}

		// Real counting perform

			$overall_list = "";

			if($this->get_values["mit"] == "real") {

				$overall_center = 0;

				foreach ($sorrend as $nr => $this_key) {

					$this_keys = explode(",", $this_key);
					$NumOfSpecs = count($this_keys);
					$grouping = array();

					foreach ($tomb2[$this_key] as $this_faj => $arri) {
					
						foreach ($arri as $unip => $count) {

							//if($unip == 'P11792') print "<BR>"."HEB - " . $this_key . "<BR><BR>" . print_r($count);

							if( count($count) < ($NumOfSpecs - 1) ) continue;

							if(! array_key_exists($unip, $tomb[$this_faj]) && $this->get_values["inside"]  ) continue;

							if( array_key_exists($unip, $voltmar[$this_faj]) ) continue;

							//if($unip == 'P11792') print "<BR><BR>"."HELLOOOOOOOOOO" . $this_key . "<BR><BR>";							

							$grouping[$this->unip2groupID[$unip]] = $unip;
							

						}

					}

					$ListOfAll = array();
					$ListOfGroups = array();
					$MidThisKeys = array();

					foreach ($this_keys as $k => $v) $MidThisKeys[] = $fajKeys_lil2mid[strtoupper($v)];

					foreach ($grouping as $groupID => $unipX) {

						if($NumOfSpecs != count($this->groupID2spec[$groupID])) continue;

						foreach ($this->groupID2unip[$groupID] as $unip1 => $faj1) {
							
							$voltmar[$fajKeys_mid2lil[$faj1]][$unip1] = $unip1;
							$ListOfAll[$unip1] = $faj1;
		
							foreach ($MidThisKeys as $k => $faj2) {

								if(! array_key_exists($faj2, $this->mappings[$faj1][$unip1])) continue;

								foreach ($this->mappings[$faj1][$unip1][$faj2] as $k2 => $unip2) {

									$voltmar[$fajKeys_mid2lil[$faj2]][$unip2] = $unip2;
									$ListOfAll[$unip2] = $faj2;

								}

							}

						}

						foreach ($ListOfAll as $unip1 => $faj1) {

							if($NumOfSpecs != count($this->groupID2spec[$this->unip2groupID[$unip1]]) ) continue;

							if(! array_key_exists($this->unip2groupID[$unip1], $ListOfGroups)) $ListOfGroups[$this->unip2groupID[$unip1]] = array($faj1 => array($unip1 => ((array_key_exists($unip1, $this->protein_list[$fajKeys_mid2lil[$faj1]])) ? "<STRONG>".$unip1."</STRONG>" : $unip1 )));
							if(! array_key_exists($faj1, $ListOfGroups[$this->unip2groupID[$unip1]])) $ListOfGroups[$this->unip2groupID[$unip1]][$faj1] = array($unip1 => ((array_key_exists($unip1, $this->protein_list[$fajKeys_mid2lil[$faj1]])) ? "<STRONG>".$unip1."</STRONG>" : $unip1 ));
							else $ListOfGroups[$this->unip2groupID[$unip1]][$faj1][$unip1] = ((array_key_exists($unip1, $this->protein_list[$fajKeys_mid2lil[$faj1]])) ? "<STRONG>".$unip1."</STRONG>" : $unip1 );

						}						

						$tomb_count[$this_key] = count($ListOfGroups);

						$this->containertable[$this_key] = $ListOfGroups;

						if($this_key == $sorrend[0]) {

							$overall_center++;
							
							//$overall_list .= "From: $this_faj: ";
							$overall_list .= "$faj1 $unip1: " . $unip2 . " ($faj2)\n";
							//$overall_list .= " (".implode(",", $count).") \n";

						}

					}

				}

			}

		// INFORMACIOK KIIRASA

			$current_info = $this->informaciok;
			$this->informaciok = "";

			$this->informaciok .= "The Query was asked ". count($spec)." species out of 7.";

			foreach ($this->protein_list2 as $this_faj => $arri) {

				$this->informaciok .= "The " . $faj[strtolower($this_faj)]["mid"] . " contains: " . count($arri) . " query UniProt IDs.<BR>\n";

				if(! $this->get_values["inside"]) $tomb_count[$this_faj] = count($tomb[$this_faj]);
				else $tomb_count[$this_faj] = count($arri) - $tomb_count[$this_faj];

			}

			//$this->informaciok .= "<BR><BR>In Overall Center are ($overall_center) :: <BR> <TEXTAREA cols=70 rows=10>".$overall_list."</TEXTAREA>";

			$this->informaciok .= $current_info;

			self::CreateTable();
			self::Kiir($svg_diagram->svg, $tomb_count, $translator);
	}

	private function mappings_cycle($keys, $tomb, $fajKeys_mid2lil){

		/*

			$keys ->  array [SPEC_ID as key] : array [ALL_KEYS as value] - ex. [AT][0] => AT,CE,DM,DR,HS,SC,SP
			$tomb ->  array [ALL_KEYS] /ordered as longest 2 shortest/ : value: empty array[] - ex. [AT,CE,DM,DR,HS,SC,SP][]
			$this->mappings --> format :: $this->mappings [$faj1][$unip1][$faj2][] = $unip2;

		*/

			$mappins_cylce_time = microtime(true);
			$pair_container = array();

			// let see all the options

			foreach ($fajKeys_mid2lil as $mid => $lil) {

				$faj1_nev = $lil;
				$this_tomb = array();
				$this_tomb2 = array();
				$this_lista = array();
				
				foreach ($this->mappings[$mid] as $unip1 => $arri2) {

					if(! array_key_exists($unip1, $this->protein_list2[ $faj1_nev ])) continue;
				
					foreach ($arri2 as $faj2 => $arri3) {

						$faj2_nev = $fajKeys_mid2lil[$faj2];

						if(! array_key_exists($faj2_nev, $this_tomb)) $this_tomb[$faj2_nev] = array();
						if(! array_key_exists($faj2_nev, $this_tomb2)) $this_tomb2[$faj2_nev] = array();
					
						foreach ($arri3 as $n => $unip2) {

							if(! array_key_exists($unip2, $this->protein_list2[ $faj2_nev ])) continue;

							$this_tomb[$faj2_nev][$unip2] = $unip2;

							if(! array_key_exists($unip2, $this_tomb2[$faj2_nev])) $this_tomb2[$faj2_nev][$unip2] = array($unip1);
							else $this_tomb2[$faj2_nev][$unip2][] = $unip1;	
						}
					}

					$this_lista[$unip1] = 1;			
				}

				foreach ($keys[$lil] as $n => $key) {

					// adding to key values each pair that we know already
					// if the key is only the identifier we add just a list of proteins

					if($key == $lil) $tomb[$key] = $this_lista;
					else $tomb[$key][$lil] = $this_tomb;				
				}

				$pair_container[$lil] = $this_tomb2;			
			}

			$this->informaciok .= TimeEnd($mappins_cylce_time, "Mappings Cycle");

			// pair containers: [faj1][faj2][unip2][unip1]
			// tomb: [faj1][faj2][unip2]

			return array("pair_container" => $pair_container, "tomb" => $tomb);
	}

	private function Kiir($svg_diagram, $tomb_count, $translator){

		$csere = $svg_diagram;

		foreach ($tomb_count as $key => $count) {
			$mit_csereljek = ">" . $translator[$key] . "<";
			$mire_csereljem = ">" . $count . "<";
			$csere = str_replace($mit_csereljek, $mire_csereljem, $csere);
		}

		$names = array("NameA", "NameB", "NameC", "NameD", "NameE", "NameF", "NameG");
		$i = 0;
		
		foreach ($this->species["mid"] as $key => $mid) {
			$mit_csereljek = ">" . $names[$i] . "<";
			$mire_csereljem = ">" . $mid. "<";
			$csere = str_replace($mit_csereljek, $mire_csereljem, $csere);
			$i++;
		}

		$this->printelni = $csere;
		return true;
	}

	public function CreateTable(){

		// content

		$id = 0;

		$print_title = true;
		$this->printertable .= "<DIV align='center'>\n<TABLE id='mytable' width='90%'>\n";

		$table = "";

		$table .= "<THEAD>\n";
		$table .= "<TR>\n";
		$table .= "<TH><B>Group</B></TH>\n";
		$table .= "<TH><B>Weighted Av. H/M</B></TH>\n"; 
		$table .= "<TH><B>Total H/M ratio</B></TH>\n";
		$table .= "<TH><B>Hits in all species</B></TH>\n";
		$table .= "<TH><B>Tot. Members</B></TH>\n"; 
		$table .= "<TH><B>Hits</B></TH>\n"; 
		$table .= "<TH><B>List of Members</B></TH>\n"; 
		$table .= "<TH><B>More</B></TH>\n"; 
		$table .= "</TR>\n";		
		$table .= "</THEAD>\n";
		$table .= "<TBODY>\n";

		$this->printertable .= $table;

		foreach ($this->containertable as $this_key => $groups) {

			$id++;

			$this_keys = explode(",", $this_key);
			$numTDsize = 100 / ( (count($this->species) * 2 ) + 5 );

			$table = "";
			
			// Printing headers for table


			$numMeasureTotalSpecies = count($this_keys);
			$numRowsInTable = 0;

			// Foreaching each of the Orthology Groups

			foreach ($groups as $groupID => $arri2) {

				$row = array();

				$row[] = "<TR>\n";
				$row[] = "\t<TD>$groupID (".$numMeasureTotalSpecies.")</TD>\n";

				$numMeasureTotalSpeciesHit = 0;
				$numMeasureTotalMember = 0;
				$numMeasureTotalHit = 0;
				$numMeasureTotalRatio = 0;

				$arrMeasureMember = array();
				$arrMeasureHit = array();
				$arrMeasureRatio = array(); 

				$arrMembers = array();
				
				foreach ($this_keys as $k => $v) {

					if( ! array_key_exists( $this->faj[strtolower($v)]["mid"], $groups[$groupID]) ) continue;

					$txtListOfProteins = implode(", ", $groups[$groupID][$this->faj[strtolower($v)]["mid"]]);

					$arrMeasureMember[$v] = count($groups[$groupID][$this->faj[strtolower($v)]["mid"]]);
					$arrMeasureHit[$v] = substr_count($txtListOfProteins, "STRONG") / 2;

					if($arrMeasureHit[$v] != 0 ) $arrMeasureRatio[$v] = $arrMeasureHit[$v] / $arrMeasureMember[$v];
					else $arrMeasureRatio[$v] = "0";

					if($arrMeasureHit[$v] > 0) $arrMembers[] = $this->faj[strtolower($v)]["mid"];					

					$numMeasureTotalMember += $arrMeasureMember[$v];
					$numMeasureTotalHit += $arrMeasureHit[$v];

					$numMeasureTotalSpeciesHit = ((strpos($txtListOfProteins, "STRONG") == true) ? ($numMeasureTotalSpeciesHit+1) : $numMeasureTotalSpeciesHit ); 

				}

				if($numMeasureTotalSpeciesHit < 2) continue;

				$row[] = self::TableRowStatistics(2, $numTDsize, $this_keys, $numMeasureTotalSpecies, $numMeasureTotalSpeciesHit, $numMeasureTotalMember, $numMeasureTotalHit, $numMeasureTotalRatio, $arrMeasureMember, $arrMeasureHit, $arrMeasureRatio );

				$row[] = "\t<TD>" . number_format(($numMeasureTotalMember / count($this_keys)), 3) . "</TD>\n";

				$row[] = "\t<TD>" . $numMeasureTotalSpeciesHit . " hit id(s) in " . $numMeasureTotalSpecies . " species" . "</TD>\n";

				$row[] = "\t<TD>$numMeasureTotalMember unique UniProt iDs belonging to this eggNOG Group in ". count($arrMembers) ." hit species</TD>\n";
				$row[] = "\t<TD>$numMeasureTotalSpeciesHit</TD>\n";
				$row[] = "\t<TD>" . implode(", ", $arrMembers) . "</TD>\n";
				$row[] = "\t<TD><i><A href='http://eggnogdb.embl.de/#/app/results?target_nogs=$groupID' target='_blank'>link to eggNOG</A></i></TD>\n";

				$row[] = "</TR>\n";

				$table .= implode("", $row);

			}

			$this->printertable .= $table;

		}

		$table = "";

		$table .= "</TBODY>\n";
		$table .= "<THEAD>\n";
		$table .= "<TR>\n";
		$table .= "<TH><B>Group</B></TH>\n";
		$table .= "<TH><B>Weighted Av. H/M</B></TH>\n"; 
		$table .= "<TH><B>Total H/M ratio</B></TH>\n";
		$table .= "<TH><B>Hits in Specs</B></TH>\n";
		$table .= "<TH><B>Tot. Members</B></TH>\n"; 
		$table .= "<TH><B>Hits</B></TH>\n"; 
		$table .= "<TH><B>List of Members</B></TH>\n"; 
		$table .= "<TH><B>More</B></TH>\n"; 
		$table .= "</TR>\n";		
		$table .= "</THEAD>\n";

		$this->printertable .= $table;

		$this->printertable .= "</TABLE>\n";
		$this->printertable .= "</DIV>";


	}

	private function TableRowStatistics($numText, $numTDsize, $this_keys, $numMeasureTotalSpecies, $numMeasureTotalSpeciesHit, $numMeasureTotalMember, $numMeasureTotalHit, $numMeasureTotalRatio, $arrMeasureMember, $arrMeasureHit, $arrMeasureRatio )  {


		if($numText == 0) {

			$txtReturn = "\t<TD width=\"" . $numTDsize . "%\">";
			$txtReturn .= "TOTAL SPECIES #:<BR>";
			$txtReturn .= "HIT SPECIES #: <BR>";
			$txtReturn .= "TOTAL MEMBER #: <BR>";
			$txtReturn .= "AVERAGE MEMBER #: <BR>";
			$txtReturn .= "TOTAL HIT #: <BR>";
			$txtReturn .= "TOTAL H/M Ratio: <BR>";
			$txtReturn .= "</TD>\n";

			return $txtReturn; 
		}

		elseif($numText == 1) {

			$txtReturn = "\t<TD width=\"" . $numTDsize . "%\">";
			$txtReturn .= $numMeasureTotalSpecies . "<BR>";
			$txtReturn .= $numMeasureTotalSpeciesHit . "<BR>";
			$txtReturn .= $numMeasureTotalMember . "<BR>";
			$txtReturn .= number_format(($numMeasureTotalMember / count($this_keys)), 3) . "<BR>";
			$txtReturn .= $numMeasureTotalHit . "<BR>";
			$txtReturn .= number_format(($numMeasureTotalHit / $numMeasureTotalMember), 3);
			$txtReturn .= "</TD>\n";

			return $txtReturn; 
		}

		elseif($numText == 2) {
			
			$numThisRatio = 0;
			foreach ($arrMeasureRatio as $k => $v) $numThisRatio += $v;
		
			$numThisRatio = $numThisRatio / count($arrMeasureRatio);

			$txtReturn = "\t<TD>";
			$txtReturn .= number_format($numThisRatio, 6);
			$txtReturn .= "</TD>\n";

			return $txtReturn;
		}

		elseif($numText == 3) {

			$numThisRatio = 0;

			foreach ($arrMeasureRatio as $k => $v) $numThisRatio += $v;
		
			$numThisRatio = $numThisRatio / count($arrMeasureRatio);
			$numThisRatio = $numThisRatio * ($numMeasureTotalSpecies / 7); 

			$txtReturn = "\t<TD width=\"" . $numTDsize . "%\">";
			$txtReturn .= $numThisRatio . "<BR>";
			$txtReturn .= "</TD>\n";

			return $txtReturn;
		}

		elseif($numText == 4) {

			$numThisRatio = 0;
			
			foreach ($arrMeasureRatio as $k => $v) $numThisRatio += $v;
		
			$numThisRatio = $numThisRatio / count($arrMeasureRatio);
			$numThisRatio = $numThisRatio * ($numMeasureTotalSpecies / 7); 

			$numTotalHitDivTotalMember = ($numMeasureTotalHit / $numMeasureTotalMember);

			$numThisRatio = number_format(($numThisRatio * $numTotalHitDivTotalMember), 4); 

			$txtReturn = "\t<TD width=\"" . $numTDsize . "%\">";
			$txtReturn .= $numThisRatio . "<BR>";
			$txtReturn .= "</TD>\n";

			return $txtReturn;
		}

	}
}

if( isset($_GET["ok"])) {

	// Script Run

		$eredmeny = new Lekeres($files, $folders4svg, $faj, $given_values, $gos);

	// Print Out

		$kiiras = file_put_contents($files["output"], $eredmeny->printelni);

		print PrintThingsOut($eredmeny->printelni, $eredmeny->informaciok, $eredmeny->species_number, $this_file, $go, $gos, $faj);
		print $eredmeny->printertable;

}

else print PrintThingsOut(false, false, false, $this_file, $go, $gos, $faj);


// End

	print TimeEnd($time_start);

?>