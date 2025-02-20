<?php
declare(strict_types=1);
/**
 * This file is part of the LiveVoting Repository Object plugin for ILIAS.
 * This plugin allows to create real time votings within ILIAS.
 *
 * The LiveVoting Repository Object plugin for ILIAS is open-source and licensed under GPL-3.0.
 * For license details, visit https://www.gnu.org/licenses/gpl-3.0.en.html.
 *
 * To report bugs or participate in discussions, visit the Mantis system and filter by
 * the category "LiveVoting" at https://mantis.ilias.de.
 *
 * More information and source code are available at:
 * https://github.com/surlabs/LiveVoting
 *
 * If you need support, please contact the maintainer of this software at:
 * info@surlabs.es
 *
 */

namespace LiveVoting\platform;

use Exception;
use ilDBInterface;

/**
 * Class LiveVotingDatabase
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class LiveVotingDatabase {
    private ilDBInterface $db;
    private array $allowedTables = array(
        "rep_robj_xlvo_cat",
        "rep_robj_xlvo_config_n",
        "rep_robj_xlvo_option_n",
        "rep_robj_xlvo_player_n",
        "rep_robj_xlvo_round_n",
        "rep_robj_xlvo_votehist",
        "rep_robj_xlvo_vote_n",
        "rep_robj_xlvo_voting_n",
        "xlvo_config",
        "xlvo_voter"
    );

    public function __construct()
    {
        global $DIC;

        $this->db = $DIC->database();
    }

    /**
     * Inserts a new row in the database
     *
     * Usage: LiveVotingDatabase::insert('table_name', ['column1' => 'value1', 'column2' => 'value2']);
     *
     * @param string $table
     * @param array $data
     * @return void
     * @throws LiveVotingException
     */
    public function insert(string $table, array $data): void
    {
        if (!$this->isTableAllowed($table)) {
            throw new LiveVotingException("Table " . $table . " not allowed");
        }

        try {
            $this->db->query("INSERT INTO " . $table . " (" . implode(", ", array_keys($data)) . ") VALUES (" . implode(", ", array_map(function ($value) {
                    return $this->db->quote($value);
                }, array_values($data))) . ")");
        } catch (Exception $e) {
            throw new LiveVotingException($e->getMessage());
        }
    }

    /**
     * Inserts a new row in the database, if the row already exists, updates it
     *
     * Usage: LiveVotingDatabase::insertOnDuplicatedKey('table_name', ['column1' => 'value1', 'column2' => 'value2']);
     *
     * @param string $table
     * @param array $data
     * @return void
     * @throws LiveVotingException
     */
    public function insertOnDuplicatedKey(string $table, array $data): void
    {
        if (!$this->isTableAllowed($table)) {
            throw new LiveVotingException("Table " . $table . " not allowed");
        }

        try {
            $this->db->query("INSERT INTO " . $table . " (" . implode(", ", array_keys($data)) . ") VALUES (" . implode(", ", array_map(function ($value) {
                    return $this->db->quote($value);
                }, array_values($data))) . ") ON DUPLICATE KEY UPDATE " . implode(", ", array_map(function ($key, $value) {
                    return $key . " = " . $value;
                }, array_keys($data), array_map(function ($value) {
                    return $this->db->quote($value);
                }, array_values($data)))));
        } catch (Exception $e) {
            throw new LiveVotingException($e->getMessage());
        }
    }

    /**
     * Updates a row/s in the database
     *
     * Usage: LiveVotingDatabase::update('table_name', ['column1' => 'value1', 'column2' => 'value2'], ['id' => 1]);
     *
     * @param string $table
     * @param array $data
     * @param array $where
     * @return void
     * @throws LiveVotingException
     */
    public function update(string $table, array $data, array $where): void
    {
        if (!$this->isTableAllowed($table)) {
            throw new LiveVotingException("Table " . $table . " not allowed");
        }

        try {
            $this->db->query("UPDATE " . $table . " SET " . implode(", ", array_map(function ($key, $value) {
                    return $key . " = " . $value;
                }, array_keys($data), array_map(function ($value) {
                    return $this->db->quote($value);
                }, array_values($data)))) . " WHERE " . implode(" AND ", array_map(function ($key, $value) {
                    return $key . " = " . $value;
                }, array_keys($where), array_map(function ($value) {
                    return $this->db->quote($value);
                }, array_values($where)))));
        } catch (Exception $e) {
            throw new LiveVotingException($e->getMessage());
        }
    }

    /**
     * Deletes a row/s in the database
     *
     * Usage: LiveVotingDatabase::delete('table_name', ['id' => 1]);
     *
     * @param string $table
     * @param array $where
     * @return void
     * @throws LiveVotingException
     */
    public function delete(string $table, array $where): void
    {
        if (!$this->isTableAllowed($table)) {
            throw new LiveVotingException("Table " . $table . " not allowed");
        }

        try {
            $this->db->query("DELETE FROM " . $table . " WHERE " . implode(" AND ", array_map(function ($key, $value) {
                    return $key . " = " . $value;
                }, array_keys($where), array_map(function ($value) {
                    return $this->db->quote($value);
                }, array_values($where)))));
        } catch (Exception $e) {
            throw new LiveVotingException($e->getMessage());
        }
    }

    /**
     * Selects a row/s in the database
     *
     * Usage: LiveVotingDatabase::select('table_name', ['id' => 1]);
     *
     * @param string $table
     * @param array|null $where
     * @param array|null $columns
     * @param string|null $extra
     * @return array
     * @throws LiveVotingException
     */
    public function select(string $table, ?array $where = null, ?array $columns = null, ?string $extra = ""): array
    {
        if (!$this->isTableAllowed($table)) {
            throw new LiveVotingException("Table " . $table . " not allowed");
        }

        try {
            $query = "SELECT " . (isset($columns) ? implode(", ", $columns) : "*") . " FROM " . $table;

            if (isset($where)) {
                $query .= " WHERE " . implode(" AND ", array_map(function ($key, $value) {
                        return $key . " = " . $value;
                    }, array_keys($where), array_map(function ($value) {
                        return $this->db->quote($value);
                    }, array_values($where))));
            }

            if (strpos(strtoupper($extra), "DROP") !== false || strpos(strtoupper($extra), "DELETE") !== false || strpos(strtoupper($extra), "UPDATE") !== false || strpos(strtoupper($extra), "INSERT") !== false || strpos(strtoupper($extra), "ALTER") !== false || strpos(strtoupper($extra), "CREATE") !== false || strpos(strtoupper($extra), "TRUNCATE") !== false || strpos(strtoupper($extra), ";") !== false) {
                throw new LiveVotingException("You can't modify the query with this extra");
            }

            $query .= " " . $extra;

            $result = $this->db->query($query);

            $rows = [];

            while ($row = $this->db->fetchAssoc($result)) {
                $rows[] = $row;
            }

            return $rows;
        } catch (Exception $e) {
            throw new LiveVotingException($e->getMessage());
        }
    }

    /**
     * Returns the next id for a table
     *
     * Usage: LiveVotingDatabase::nextId('table_name');
     *
     * @param string $table
     * @return int
     * @throws LiveVotingException
     */
    public function nextId(string $table): int
    {
        if (!$this->isTableAllowed($table)) {
            throw new LiveVotingException("Table " . $table . " not allowed");
        }

        try {
            return $this->db->nextId($table);
        } catch (Exception $e) {
            throw new LiveVotingException($e->getMessage());
        }
    }

    public function isTableAllowed(string $table): bool
    {
        return in_array($table, $this->allowedTables);
    }
}