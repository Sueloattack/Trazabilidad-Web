<?php
declare(strict_types=1);

class ConnectionFox
{
    private static $conexion = null;

    // Especificamos que devuelve un objeto PDO
    public static function con(): PDO
    {
        if (self::$conexion === null) {
            try {
                self::$conexion = self::connect();
            } catch (\Exception $e) {
                //die("No se pudo conectar a GEMA...");
                die("ERROR DETALLADO AL CONECTAR A FOXPRO: " . $e->getMessage()); 
            }
        }
        return self::$conexion;
    }

    // También especificamos que devuelve un PDO
    private static function connect(): PDO
    {
        $dsn = "odbc:Driver={Microsoft Visual FoxPro Driver};".
               "SourceType=DBF;SourceDB=C:\\gl\\;Exclusive=No;".
               "Collate=Machine;NULL=NO;DELETED=NO;BACKGROUNDFETCH=NO;DELETED=YES;";

        return new PDO(
            $dsn,
            "",
            "",
            [
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION   
            ]
        );
    }
}
?>
