<?php
class DataModel {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // Obtiene el total de filas (para calcular páginas)
    public function getTotalRows(): int {
        // Usar el mismo JOIN que consideras correcto para la lógica de negocio (como en Sync.php)
        $stmt = $this->pdo->query("
            SELECT COUNT(fg.id) 
            FROM factura_glosas fg
            INNER JOIN envio_glosas eg ON fg.id = eg.id_facturaglosas
            "); // Asumiendo que esta es la relación principal entre las tablas
            return (int) $stmt->fetchColumn();
    }

    // Obtiene filas con límite y offset para paginación
    public function getRows(int $limit, int $offset): array {
        // Aseguramos que seleccionamos fg.id y usamos el mismo JOIN
        $sql = "
            SELECT
                fg.id, 
                fg.serie,
                fg.docn,
                fg.nit_tercero,
                fg.nom_tercero,
                eg.f_respuesta_g,
                fg.cuenta_cobro,
                fg.fecha_resp
            FROM factura_glosas fg
            INNER JOIN envio_glosas eg ON fg.id = eg.id_facturaglosas 
            ORDER BY fg.serie, fg.docn 
            LIMIT :limit OFFSET :offset
            ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>