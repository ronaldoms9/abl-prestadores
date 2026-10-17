<?php

/*
|--------------------------------------------------------------------------
| Usuários iniciais de um servidor (fatia 9)
|--------------------------------------------------------------------------
|
| Fora do ambiente local, os seeders não usam senha conhecida: o
| administrador vem de ADMIN_EMAIL e os usuários de homologação (um por
| perfil) de HOMOLOGACAO_USUARIOS ("rh:maria@x.com,gestor:joao@x.com").
| As senhas são aleatórias e aparecem uma única vez no terminal.
|
*/

return [
    'admin_email' => env('ADMIN_EMAIL'),
    'homologacao_usuarios' => env('HOMOLOGACAO_USUARIOS', ''),
];
