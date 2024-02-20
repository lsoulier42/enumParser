<?php

$dir = "./src/Enum/";
$files = scandir($dir);
$excludedFiles = ['.', '..', 'BaseEnum.php'];
$newDir = "./src/NewEnum/";
foreach ($files as $file) {
    if (in_array($file, $excludedFiles, true)) {
        continue;
    }
    $fileAsString = file_get_contents($dir . $file);
    if (preg_match("/enum ([a-zA-Z]+):/", $fileAsString)) {
        continue;
    }
    $newFileAsString = convertToPhp8Enum($fileAsString);
    file_put_contents($newDir . $file, $newFileAsString);
}


/**
 * @param string $fileAsString
 * @return string
 * @throws Exception
 */
function convertToPhp8Enum(string $fileAsString): string
{
    $typeEnum = detectTypeEnum($fileAsString);
    changeClassType($fileAsString, $typeEnum);
    replaceMethodByCase($fileAsString);
    replaceGetValue($fileAsString);
    replaceGetAll($fileAsString);
    replaceByValue($fileAsString);
    $lines = explode("\n", $fileAsString);
    foreach ($lines as $i => $line) {
        if (isPhpDocPattern($line)) {
            unset($lines[$i]);
        } elseif (isConstantLine($line)) {
            $lines[$i] = preg_replace("/(\s)public const ([A-Z_]+)(\s+)= (.*);/", "$1case $2 = $4;", $line);
        } elseif (preg_match("/public function __toString\(\)/", $line)) {
            $lines[$i] = preg_replace("/(\s+)public function (__toString\(\))/", "$1public function trans()", $line);
        }
    }
    return implode("\n", $lines);
}

/**
 * @param string $fileAsString
 * @return string
 */
function detectTypeEnum(string $fileAsString): string
{
    return preg_match("/public const (.*) = (['\"])(.*)(['\"]);/", $fileAsString) ? "string" : "int";
}

/**
 * @param string $fileAsString
 * @param string $typeEnum
 * @return void
 * @throws Exception
 */
function changeClassType(string &$fileAsString, string $typeEnum): void
{
    $classLine = preg_replace("/class (.*) extends BaseEnum/", "enum $1: $typeEnum", $fileAsString);
    if (!is_string($classLine)) {
        throw new Exception("Error changing className");
    }
    $fileAsString = $classLine;
}

/**
 * @param string $fileAsString
 * @return void
 * @throws Exception
 */
function replaceGetValue(string &$fileAsString): void
{
    $getValue = preg_replace('/\$(this|e|method)->getValue\(\)/', '\$$1->value', $fileAsString);
    if (!is_string($getValue)) {
        throw new Exception("Error changing getValue method");
    }
    $getValueOnCase = preg_replace('/self::([A-Z_]+)->getValue\(\)/', 'self::$1->value', $getValue);
    if (!is_string($getValueOnCase)) {
        throw new Exception("Error changing getValueOnCase method");
    }
    $fileAsString = $getValueOnCase;
}

function replaceMethodByCase(string &$fileAsString): void
{
    $methodByCase = preg_replace('/self::([A-Z_]+)\(\)/', 'self::$1', $fileAsString);
    if (!is_string($methodByCase)) {
        throw new Exception("Error changing methodByCase");
    }
    $fileAsString = $methodByCase;
}

/**
 * @param string $line
 * @return bool
 */
function isPhpDocPattern(string $line): bool
{
    $patterns = ["\/\*\*", " \*",  " \*\/"];
    foreach ($patterns as $pattern) {
        if (preg_match("/(^|\s+)$pattern/", $line) != 0) {
            return true;
        }
    }
    return false;
}

/**
 * @param string $line
 * @return bool
 */
function isConstantLine(string $line): bool
{
    return preg_match("/(\s)public const /", $line) != 0;
}

/**
 * @param string $fileAsString
 * @return void
 * @throws Exception
 */
function replaceGetAll(string &$fileAsString): void
{
    $getAll = preg_replace('/self::getAll\(\)/', 'self::cases()', $fileAsString);
    if (!is_string($getAll)) {
        throw new Exception("Error changing getAll()");
    }
    $fileAsString = $getAll;
}

/**
 * @param string $fileAsString
 * @return void
 * @throws Exception
 */
function replaceByValue(string &$fileAsString): void
{
    $byValue = preg_replace('/self::byValue\((.*)\)/', 'self::tryFrom($1)', $fileAsString);
    if (!is_string($byValue)) {
        throw new Exception("Error changing byValue");
    }
    $fileAsString = $byValue;
}
