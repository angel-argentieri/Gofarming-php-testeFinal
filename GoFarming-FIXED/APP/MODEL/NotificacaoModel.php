<?php

class NotificacaoModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function criar($id_usuario, $titulo, $mensagem, $tipo = 'rega', $id_planta = null) {
        $stmt = $this->db->prepare("
            INSERT INTO Notificacoes (id_usuario, id_planta, tipo, titulo, mensagem)
            VALUES (:id_usuario, :id_planta, :tipo, :titulo, :mensagem)
        ");
        $stmt->execute([
            ':id_usuario' => $id_usuario,
            ':id_planta'  => $id_planta,
            ':tipo'       => $tipo,
            ':titulo'     => $titulo,
            ':mensagem'   => $mensagem,
        ]);
    }

    public function jaExisteHoje($id_usuario, $id_planta, $tipo = 'rega') {
        $stmt = $this->db->prepare("
            SELECT id FROM Notificacoes
            WHERE id_usuario = :id_usuario AND id_planta = :id_planta AND tipo = :tipo
            AND DATE(criada_em) = CURDATE()
        ");
        $stmt->execute([
            ':id_usuario' => $id_usuario,
            ':id_planta'  => $id_planta,
            ':tipo'       => $tipo,
        ]);
        return (bool) $stmt->fetch();
    }

    public function listarPorUsuario($id_usuario, $limite = 30) {
        $stmt = $this->db->prepare("
            SELECT id, id_planta, tipo, titulo, mensagem, lida, criada_em
            FROM Notificacoes
            WHERE id_usuario = :id_usuario
            ORDER BY criada_em DESC
            LIMIT :limite
        ");
        $stmt->bindValue(':id_usuario', $id_usuario, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function contarNaoLidas($id_usuario) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total FROM Notificacoes
            WHERE id_usuario = :id_usuario AND lida = 0
        ");
        $stmt->execute([':id_usuario' => $id_usuario]);
        return (int) ($stmt->fetch()['total'] ?? 0);
    }

    public function marcarComoLida($id, $id_usuario) {
        $stmt = $this->db->prepare("
            UPDATE Notificacoes SET lida = 1
            WHERE id = :id AND id_usuario = :id_usuario
        ");
        return $stmt->execute([':id' => $id, ':id_usuario' => $id_usuario]);
    }

    public function marcarTodasComoLidas($id_usuario) {
        $stmt = $this->db->prepare("
            UPDATE Notificacoes SET lida = 1
            WHERE id_usuario = :id_usuario AND lida = 0
        ");
        return $stmt->execute([':id_usuario' => $id_usuario]);
    }
}
