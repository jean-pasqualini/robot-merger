<?php


class Matrice {

    private $data = [];

    private $count;

    private $type;

    private $strict = true;

    private $columnsKeys = [];

    public function setColumnDef(int $count, string $type, array $columnsKeys = [], bool $strict = true) {
        $this->count = $count;
        $this->type = $type;
        $this->columnsKeys = $columnsKeys;
        $this->strict = $strict;
    }

    /**
     * @param array $line
     * @param string|null $lineName
     * @throws Exception
     */
    public function addLine(array $line, string $lineName = null) {
        if (\count($line) != $this->count) {
            if (!$this->strict) {
                return;
            }

            throw new \Exception(sprintf(
                'this line not match size expected (expected: %d, actual: %d)',
                $this->count,
                \count($line)
            ));
        }

        if (!$lineName) {
            $lineName = \count($this->data) - 1 ?: 0;
        }

        foreach ($line as $key => $value) {
            $this->addLineValue($lineName, $key, $value);
        }
    }

    /**
     * @param $lineName
     * @param $value
     * @throws Exception
     */
    private function addLineValue($lineName, string $key = null, $value)
    {
        $composedType = explode('::', $this->type);

        if (gettype($value) !== $composedType[0]) {
            if (!$this->strict) {
                return;
            }

            throw new \Exception(sprintf(
                'this line not match type expected (expected: %s, actual: %s)',
                $composedType[0],
                gettype($value)
            ));
        }

        if (!in_array($key, $this->columnsKeys)) {
            if (!$this->strict) {
                return;
            }

            throw new \Exception(sprintf(
                'this key not match type expected (expected: %s, actual: %s)',
                implode(', ', $this->columnsKeys),
                $key
            ));
        }

        if (!empty($composedType[1]) && get_class($value) !== $composedType[1]) {
            return;
        }

        $this->data[$lineName][$key] = $value;
    }


    public function getColumn($name)
    {
        return array_column($this->data, $name);
    }

    public function columns()
    {
        $firstLine = reset($this->data);
        return array_map(function($key) {
            return array_column($this->data, $key);
        }, array_keys($firstLine));
    }

    public function column($key)
    {
        return array_column($this->data, $key);
    }

    public function lines()
    {
        return $this->data;
    }

    public function line($key)
    {
        return $this->data[$key];
    }
}

class PotentialMatriceCalculator extends Matrice {
    public function calcul(array $coeffs) {
        $result = [];

        foreach ($this->lines() as $lineName => $line) {
            $potential = 0;
            foreach ($line as $columnName => $value) {
                $columnValues = $this->column($columnName);
                $potential += (($value / max($columnValues)) * $coeffs[$columnName]);
            }

            $result[$lineName] = $potential;
        }

        return $result;
    }
}


class PotentialEtudiantMatriceCalculator extends PotentialMatriceCalculator {

    public function __construct()
    {
        $this->setColumnDef(3, 'integer', ['red', 'blue', 'green'], true);
    }

    /**
     * @param string $name
     * @param array $notes
     * @throws Exception
     */
    public function addEtudiantNotes(string $name, array $notes)
    {
        $this->addLine($notes, $name);
    }
}


$potentialEtudiantCalculator = new PotentialEtudiantMatriceCalculator();
$potentialEtudiantCalculator->addEtudiantNotes('etudian1', ['red' => 5, 'blue' => 10, 'green' => 15]);
$potentialEtudiantCalculator->addEtudiantNotes('etudian2', ['red' => 5, 'blue' => 10, 'green' => 15]);
$result = $potentialEtudiantCalculator->calcul(
    ['red' => 0.5, 'blue' => 0.5, 'green' => 1.0]
);

var_dump($result);