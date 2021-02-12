<?php

namespace Pop\Db\Test;

use Pop\Db\Db;
use Pop\Db\Sql;
use PHPUnit\Framework\TestCase;

class SqlTest extends TestCase
{

    protected $db = null;

    public function setUp(): void
    {
        $this->db = Db::mysqlConnect([
            'database' => $_ENV['MYSQL_DB'],
            'username' => $_ENV['MYSQL_USER'],
            'password' => $_ENV['MYSQL_PASS'],
            'host'     => $_ENV['MYSQL_HOST']
        ]);
    }

    public function testInitSqlConfig()
    {
        $sql = $this->db->createSql();
        $sql->setIdQuoteType(Sql::BACKTICK);
        $sql->setPlaceholder('?');
        $this->assertInstanceOf('Pop\Db\Adapter\Mysql', $sql->db());
        $this->assertInstanceOf('Pop\Db\Adapter\Mysql', $sql->getDb());
        $this->assertEquals(Sql::BACKTICK, $sql->getIdQuoteType());
        $this->assertEquals('?', $sql->getPlaceholder());
        $this->assertEquals("`", $sql->getOpenQuote());
        $this->assertEquals("`", $sql->getCloseQuote());
        $this->assertEquals('`pop_users`.`id`', $sql->quoteId('pop_users.id'));
        $this->db->disconnect();
    }

    public function testInitSqlConfigNoQuote()
    {
        $sql = $this->db->createSql();
        $sql->setIdQuoteType(Sql::NO_QUOTE);
        $this->assertNull($sql->getOpenQuote());
        $this->assertNull($sql->getCloseQuote());
        $this->db->disconnect();
    }

    public function testSelectWithValues()
    {
        $sql = $this->db->createSql();
        $sql->select([
            'id', 'username', 'email'
        ])->from('users');

        $this->assertEquals('users', $sql->select()->getTable());
        $this->assertEquals(3, count($sql->select()->getValues()));
        $this->assertEquals('id', $sql->select()->getValue(0));
        $this->assertEquals("SELECT `id`, `username`, `email` FROM `users`", $sql->render());
        $this->db->disconnect();
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
        $this->db->disconnect();
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
        $this->db->disconnect();
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
        $this->db->disconnect();
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
        $this->db->disconnect();
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

        $this->db->disconnect();
    }

    public function testParameterCount()
    {
        $sql = $this->db->createSql();
        $this->assertEquals(0, $sql->getParameterCount());
        $sql->incrementParameterCount();
        $this->assertEquals(1, $sql->getParameterCount());
        $sql->decrementParameterCount();
        $this->assertEquals(0, $sql->getParameterCount());
    }

}