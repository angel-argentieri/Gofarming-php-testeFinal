<?php

class UsuarioModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function buscarPorEmail($email) {
        $stmt = $this->db->prepare("SELECT * FROM Usuarios WHERE email = :email");
        $stmt->bindValue(':email', $email);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function criar($nome, $email, $senha) {
        $stmt = $this->db->prepare("
            INSERT INTO Usuarios (nome, email, senha)
            VALUES (:nome, :email, :senha)
        ");
        $stmt->bindValue(':nome',  $nome);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':senha', password_hash($senha, PASSWORD_DEFAULT));
        $stmt->execute();
        return $this->db->lastInsertId();
    }
}
