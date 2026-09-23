<?php

class PlantaModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function buscarPorUsuario($id_usuario) {
        $stmt = $this->db->prepare("
            SELECT p.*,
                (SELECT r.status FROM Regas r
                   WHERE r.id_planta = p.id AND r.data_prevista = CURDATE()
                   ORDER BY r.id LIMIT 1) AS rega_hoje,
                (SELECT r.id FROM Regas r
                   WHERE r.id_planta = p.id AND r.data_prevista = CURDATE()
                   ORDER BY r.id LIMIT 1) AS rega_id,
                (SELECT MIN(r.data_prevista) FROM Regas r
                   WHERE r.id_planta = p.id AND r.status = 'pendente'
                     AND r.data_prevista >= CURDATE()) AS proxima_rega
            FROM Plantas p
            WHERE p.id_usuario = :id_usuario
            ORDER BY p.criada_em DESC
        ");
        $stmt->bindValue(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function buscarPorId($id) {
        $stmt = $this->db->prepare("SELECT * FROM Plantas WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function criar($id_usuario, $nome, $especie, $foto_url, $frequencia_rega, $vezes_por_semana, $dias_semana, $access_token, $horario_rega = '08:00') {
        $stmt = $this->db->prepare("
            INSERT INTO Plantas
                (id_usuario, nome, especie, foto_url, frequencia_rega, vezes_por_semana, dias_semana, access_token_plantid, horario_rega)
            VALUES
                (:id_usuario, :nome, :especie, :foto_url, :frequencia_rega, :vezes, :dias, :access_token, :horario_rega)
        ");
        $stmt->execute([
            ':id_usuario'      => $id_usuario,
            ':nome'            => $nome,
            ':especie'         => $especie,
            ':foto_url'        => $foto_url,
            ':frequencia_rega' => $frequencia_rega,
            ':vezes'           => $vezes_por_semana,
            ':dias'            => $dias_semana,
            ':access_token'    => $access_token,
            ':horario_rega'    => $horario_rega ?: '08:00',
        ]);
        return $this->db->lastInsertId();
    }

    public function atualizarAgenda($id, $vezes_por_semana, $dias_semana, $horario_rega = null) {
        $sql  = "UPDATE Plantas SET vezes_por_semana = :vezes, dias_semana = :dias";
        $bind = [':vezes' => $vezes_por_semana, ':dias' => $dias_semana, ':id' => $id];

        if ($horario_rega !== null) {
            $sql .= ", horario_rega = :horario";
            $bind[':horario'] = $horario_rega;
        }

        $sql .= " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($bind);
    }

    public function deletar($id) {
        $stmt = $this->db->prepare("DELETE FROM Plantas WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
