<?php

namespace Pop\Db\Test;

use Pop\Db\Db;
use Pop\Db\Sql;
use PHPUnit\Framework\TestCase;

class SqlTest extends TestCase
{

    protected $db = null;

    public function setUp()
    {
        $this->db = Db::mysqlConnect([
            'database' => 'travis_popdb',
            'username' => 'root',
            'password' => trim(file_get_contents(__DIR__ . '/tmp/.mysql')),
            'host'     => 'localhost'
        ]);
    }

    public function testSelectWithValues()
    {
        $sql = $this->db->createSql();
        $sql->select([
            'id', 'username', 'email'
        ])->from('users');
        $this->assertEquals("SELECT `id`, `username`, `email` FROM `users`", $sql->render());
    }

    public function testSelectWithNamedValues()
    {
        $sql = $this->db->createSql();
        $sql->select([
            'id',
            'user_name' => 'username',
            'email'
        ])->from('users');
        $this->assertTrue($sql->hasSelect());
        $this->assertFalse($sql->hasInsert());
        $this->assertFalse($sql->hasUpdate());
        $this->assertFalse($sql->hasDelete());
        $this->assertEquals("SELECT `id`, `username` AS `user_name`, `email` FROM `users`", $sql->render());
    }

    public function testInsertWithTable()
    {
        $sql = $this->db->createSql();
        $sql->insert('users')->values([
            'username' => 'admin'
        ]);
        $this->assertFalse($sql->hasSelect());
        $this->assertTrue($sql->hasInsert());
        $this->assertFalse($sql->hasUpdate());
        $this->assertFalse($sql->hasDelete());
        $this->assertEquals("INSERT INTO `users` (`username`) VALUES ('admin')", (string)$sql);
    }

    public function testUpdate()
    {
        $sql = $this->db->createSql();
        $sql->update('users')->values(['username' => 'admin2'])->where('id = 1');
        $this->assertFalse($sql->hasSelect());
        $this->assertFalse($sql->hasInsert());
        $this->assertTrue($sql->hasUpdate());
        $this->assertFalse($sql->hasDelete());
        $this->assertEquals("UPDATE `users` SET `username` = 'admin2' WHERE (`id` = 1)", (string)$sql);
    }

    public function testDelete()
    {
        $sql = $this->db->createSql();
        $sql->delete('users')->where('id = 1');
        $this->assertFalse($sql->hasSelect());
        $this->assertFalse($sql->hasInsert());
        $this->assertFalse($sql->hasUpdate());
        $this->assertTrue($sql->hasDelete());
        $this->assertEquals("DELETE FROM `users` WHERE (`id` = 1)", (string)$sql);
    }

    public function testReset()
    {
        $sql = $this->db->createSql();
        $sql->delete('users')->where('id = 1');
        $this->assertFalse($sql->hasSelect());
        $this->assertFalse($sql->hasInsert());
        $this->assertFalse($sql->hasUpdate());
        $this->assertTrue($sql->hasDelete());
        $sql->reset();
        $this->assertFalse($sql->hasSelect());
        $this->assertFalse($sql->hasInsert());
        $this->assertFalse($sql->hasUpdate());
        $this->assertFalse($sql->hasDelete());
    }

}