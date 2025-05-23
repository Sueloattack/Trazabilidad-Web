<?php
class DataController {
    private $model;
    private $rowsPerPage = 20;

    public function __construct(DataModel $model) {
        $this->model = $model;
    }

    public function handleRequest() {
        // Obtener página actual (por GET), default 1
        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
        if ($page < 1) $page = 1;

        $totalRows = $this->model->getTotalRows();
        $totalPages = ceil($totalRows / $this->rowsPerPage);

        $offset = ($page - 1) * $this->rowsPerPage;
        $rows = $this->model->getRows($this->rowsPerPage, $offset);

        // Cargar vista pasándole datos
        include __DIR__ . '/../../views/data_list.php';
    }
}
