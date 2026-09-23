<?php

class RegaModel {
    private $db;

    const HORIZONTE_DIAS = 60;

    public function __construct($db) {
        $this->db = $db;
    }

    public static function distribuirDias($vezesPorSemana) {
        $n = max(1, min(7, (int) $vezesPorSemana));
        $mapa = [
            1 => [1],
            2 => [1, 4],
            3 => [1, 3, 5],
            4 => [1, 3, 5, 7],
            5 => [1, 2, 3, 5, 6],
            6 => [1, 2, 3, 4, 5, 6],
            7 => [1, 2, 3, 4, 5, 6, 7],
        ];
        return $mapa[$n];
    }

    public static function normalizarDias($dias) {
        if (!is_array($dias)) {
            $dias = array_filter(explode(',', (string) $dias), 'strlen');
        }
        $dias = array_values(array_unique(array_filter(
            array_map('intval', $dias),
            fn($d) => $d >= 1 && $d <= 7
        )));
        sort($dias);
        return $dias ?: [1, 4];
    }

    public function gerarAgenda($id_planta, $diasSemana) {
        $dias = self::normalizarDias($diasSemana);

        $stmt = $this->db->prepare("
            DELETE FROM Regas
            WHERE id_planta = :id AND status = 'pendente' AND data_prevista >= CURDATE()
        ");
        $stmt->execute([':id' => $id_planta]);

        $insert = $this->db->prepare("
            INSERT IGNORE INTO Regas (id_planta, data_prevista)
            VALUES (:id_planta, :data_prevista)
        ");

        $hoje = new DateTime('today');

        for ($i = 0; $i < self::HORIZONTE_DIAS; $i++) {
            $dia = (clone $hoje)->modify("+{$i} days");
            if (!in_array((int) $dia->format('N'), $dias, true)) continue;
            $insert->execute([
                ':id_planta'     => $id_planta,
                ':data_prevista' => $dia->format('Y-m-d'),
            ]);
        }

        return $dias;
    }

    public function criarProximasRegas($id_planta, $frequencia_por_semana) {
        return $this->gerarAgenda($id_planta, self::distribuirDias($frequencia_por_semana));
    }

    public function marcarComoRegada($id_rega, $id_usuario) {
        $stmt = $this->db->prepare("
            UPDATE Regas r
            JOIN Plantas p ON p.id = r.id_planta
            SET r.status = 'concluida', r.data_regada = NOW()
            WHERE r.id = :id AND p.id_usuario = :id_usuario
        ");
        $stmt->execute([':id' => $id_rega, ':id_usuario' => $id_usuario]);
        return $stmt->rowCount() > 0;
    }

    public function proximas($id_planta, $limite = 8) {
        $stmt = $this->db->prepare("
            SELECT id, data_prevista, status
            FROM Regas
            WHERE id_planta = :id AND data_prevista >= CURDATE()
            ORDER BY data_prevista ASC
            LIMIT :limite
        ");
        $stmt->bindValue(':id', $id_planta, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function buscarPendentesDoUsuario($id_usuario) {
        $stmt = $this->db->prepare("
            SELECT r.id, r.id_planta, p.nome AS nome_planta
            FROM Regas r
            JOIN Plantas p ON p.id = r.id_planta
            WHERE p.id_usuario = :id_usuario
              AND r.data_prevista = CURDATE()
              AND r.status = 'pendente'
        ");
        $stmt->execute([':id_usuario' => $id_usuario]);
        return $stmt->fetchAll();
    }

    public function buscarPendentesHoje() {
        $stmt = $this->db->prepare("
            SELECT r.id, r.id_planta, p.nome AS nome_planta,
                   u.id AS id_usuario, u.email, u.nome AS nome_usuario
            FROM Regas r
            JOIN Plantas p ON p.id = r.id_planta
            JOIN Usuarios u ON u.id = p.id_usuario
            WHERE r.data_prevista = CURDATE() AND r.status = 'pendente'
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
