<?php

namespace Pop\Db\Test;

use Pop\Db\Db;
use PHPUnit\Framework\TestCase;

class DbTest extends TestCase
{

    public function testConnectException()
    {
        $this->expectException('Pop\Db\Exception');
        $db = Db::connect('mysql', [], 'Bad\Namespace\\');
    }

    public function testCheck()
    {
        $check = Db::check('mysql', [
            'database' => 'travis_popdb',
            'username' => 'root',
            'password' => trim(file_get_contents(__DIR__ . '/tmp/.mysql')),
            'host'     => 'localhost'
        ]);
        $this->assertTrue(($check === true));
    }

    public function testCheckError()
    {
        $check = Db::check('mysql', [
            'database' => 'bad_db',
            'username' => 'root',
            'password' => trim(file_get_contents(__DIR__ . '/tmp/.mysql')),
            'host'     => 'localhost'
        ]);
        $this->assertContains('Error: ', $check);
    }

    public function testCheckException()
    {
        $check = Db::check('mysql', [
            'database' => 'travis_popdb',
            'username' => 'root',
            'password' => trim(file_get_contents(__DIR__ . '/tmp/.mysql')),
            'host'     => 'localhost'
        ], 'Bad\Namespace\\');
        $this->assertEquals("Error: The database adapter 'Bad\Namespace\Mysql' does not exist.", $check);
    }

    public function testExecuteSqlFileException()
    {
        $this->expectException('Pop\Db\Exception');
        Db::executeSqlFile(__DIR__ . '/tmp/bad.mysql.sql', 'mysql', [
            'database' => 'travis_popdb',
            'username' => 'root',
            'password' => trim(file_get_contents(__DIR__ . '/tmp/.mysql')),
            'host'     => 'localhost',
            'prefix'   => 'ph_'
        ]);
    }

    public function testExecuteMysqlSqlFile()
    {
        Db::executeSqlFile(__DIR__ . '/tmp/users.mysql.sql', 'mysql', [
            'database' => 'travis_popdb',
            'username' => 'root',
            'password' => trim(file_get_contents(__DIR__ . '/tmp/.mysql')),
            'host'     => 'localhost',
            'prefix'   => 'ph_'
        ]);
        $db = Db::mysqlConnect([
            'database' => 'travis_popdb',
            'username' => 'root',
            'password' => trim(file_get_contents(__DIR__ . '/tmp/.mysql')),
            'host'     => 'localhost',
        ]);
        $this->assertTrue($db->hasTable('ph_users'));
        $db->query('DROP TABLE `ph_users`');
    }

    public function testExecuteSqliteSqlFile()
    {
        chmod(__DIR__ . '/tmp', 0777);
        touch(__DIR__ . '/tmp/db.sqlite');
        chmod(__DIR__ . '/tmp/db.sqlite', 0777);

        Db::executeSqlFile(__DIR__ . '/tmp/users.sqlite.sql', 'sqlite', ['database' => __DIR__ . '/tmp/db.sqlite', 'prefix' => 'ph_']);
        $db = Db::sqliteConnect(['database' => __DIR__ . '/tmp/db.sqlite']);
        $this->assertTrue($db->hasTable('ph_users'));

        unlink(__DIR__ . '/tmp/db.sqlite');
    }

}