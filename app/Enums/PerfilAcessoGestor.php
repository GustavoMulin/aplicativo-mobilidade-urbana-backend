<?php

namespace App\Enums;

enum PerfilAcessoGestor: string
{
    case ATENDIMENTO = 'atendimento';
    case SUPORTE_TECNICO = 'suporte_tecnico';
    case GESTAO = 'gestao';
    case ADMIN = 'admin';

    public function nome(): string
    {
        return match ($this) {
            self::ATENDIMENTO => 'Atendimento',
            self::SUPORTE_TECNICO => 'Suporte técnico',
            self::GESTAO => 'Gestão',
            self::ADMIN => 'Admin',
        };
    }

    public static function catalogoPermissoes(): array
    {
        return [
            'usuarios.consultar' => 'Consultar usuários, motoristas e passageiros',
            'veiculos.consultar' => 'Consultar veículos e motoristas vinculados',
            'documentos.consultar' => 'Visualizar e baixar documentos',
            'corridas.consultar' => 'Consultar corridas',
            'ajuda.gerenciar' => 'Registrar e acompanhar chamados de suporte',
            'usuarios.gerenciar' => 'Cadastrar e alterar dados operacionais',
            'veiculos.gerenciar' => 'Cadastrar e vincular veículos',
            'documentos.analisar' => 'Enviar, aprovar e reprovar documentos',
            'gestores.gerenciar' => 'Cadastrar e administrar gestores',
            'regras.gerenciar' => 'Definir os perfis de acesso',
            'configuracoes.gerenciar' => 'Administrar produtos, tarifas e configurações',
        ];
    }

    public function permissoes(): array
    {
        $consulta = ['usuarios.consultar', 'veiculos.consultar', 'documentos.consultar', 'corridas.consultar', 'ajuda.gerenciar'];

        return match ($this) {
            self::ATENDIMENTO, self::SUPORTE_TECNICO => $consulta,
            self::GESTAO => [...$consulta, 'usuarios.gerenciar', 'veiculos.gerenciar', 'documentos.analisar'],
            self::ADMIN => array_keys(self::catalogoPermissoes()),
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::ATENDIMENTO => 'Atendimento aos usuários e encaminhamento dos chamados aos responsáveis.',
            self::SUPORTE_TECNICO => 'Investigação de bugs, falhas do aplicativo e outros problemas de TI.',
            self::GESTAO => 'Cadastros, veículos e análise dos documentos da operação.',
            self::ADMIN => 'Controle completo do painel, dos gestores e dos acessos.',
        };
    }
}
