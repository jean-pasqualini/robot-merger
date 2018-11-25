<?php

class MatriceException extends \Exception {}

class Matrice implements ArrayAccess {

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

    public static function create(int $count, string $type, array $columnsKeys = [], bool $strict = true) {
        $matrice = new static();
        $matrice->setColumnDef($count, $type, $columnsKeys, $strict);

        return $matrice;
    }

    /**
     * @param array $line
     * @param string|null $lineName
     * @throws Exception
     */
    public function addLine(array $line, string $lineName = null) {
        if (\count($line) != $this->count) {
            if ($this->strict) {
                throw new MatriceException(sprintf(
                    'this line not match size expected (expected: %d, actual: %d)',
                    $this->count,
                    \count($line)
                ));
            }

            return;
        }

        if (!$lineName) {
            $lineName = \count($this->data) - 1;
            $lineName = ($lineName < 0) ? 0 : $lineName;
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
            if ($this->strict) {
                throw new MatriceException(sprintf(
                    'this line not match type expected (expected: %s, actual: %s)',
                    $composedType[0],
                    gettype($value)
                ));
            }

            return;
        }

        if ('object' === $composedType[0] && get_class($value) !== $composedType[1]) {
            if ($this->strict) {
                throw new MatriceException(sprintf(
                    'this key not match class expected (expected: %s, actual: %s)',
                    $composedType[1],
                    get_class($value)
                ));
            }

            return;
        }

        if ($this->columnsKeys && !in_array($key, $this->columnsKeys)) {
            if ($this->strict) {
                throw new MatriceException(sprintf(
                    'this key not match type expected (expected: %s, actual: %s)',
                    implode(', ', $this->columnsKeys),
                    $key
                ));
            }

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

    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->data[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->addLine($value, $offset);
    }

    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    public function __debugInfo()
    {
        return ['table' => (string) $this, 'data' => $this->data];
    }

    private function recursiveToString(array $data, int $level = 0, $separator = '|'): string
    {
        $indent = str_repeat("  ", $level);
        if (is_array($data) && is_array(reset($data))) {
            $nextLevel = $level + 1;
            $result = [];
            $result[] = $this->recursiveToString(array_merge([''], $this->columnsKeys()), $nextLevel);
            foreach ($data as $lineName => $entry) {
                $entry = array_merge([$lineName], $entry);
                $result[] = $this->recursiveToString($entry, $nextLevel);
            }
            return sprintf("%s[\n%s\n%s]", $indent, implode("\n", $result), $indent);
        }

        foreach ($data as $id => $item) {
            $data[$id] = str_pad($item, 10, ' ', STR_PAD_LEFT);
        }
        return sprintf("%s[%s]", $indent, implode($separator." ", $data));
    }

    public function __toString()
    {
        return $this->recursiveToString($this->data, 0);
    }

    private function columnsKeys()
    {
        $firstLine = reset($this->data);

        return array_keys($firstLine);
    }
}

class PotentialMatriceCalculator extends Matrice {

    public static function create(int $count, string $type = 'integer', array $columnsKeys = [], bool $strict = true) {
        $matrice = new static();
        $matrice->setColumnDef($count, $type, $columnsKeys, $strict);

        return $matrice;
    }


    public function __invoke(array $coeffs) {
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
        $this->setColumnDef(3, 'integer', [], true);
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


/*$potentialEtudiantCalculator = new PotentialEtudiantMatriceCalculator();
$potentialEtudiantCalculator->addEtudiantNotes('etudian1', ['math' => 5, 'geo' => 10, 'francais' => 15]);
$potentialEtudiantCalculator->addEtudiantNotes('etudian2', ['math' => 5, 'geo' => 10, 'francais' => 15]);

echo (string) $potentialEtudiantCalculator.PHP_EOL;*/

try {

    $potentialMatrice = PotentialMatriceCalculator::create(3);
    $potentialMatrice['m1'] = ['red' => 2, 'green' => 1, 'blue' => 4];
    $potentialMatrice['m2'] = ['red' => 2, 'green' => 3, 'blue' => 4];

    echo (string) $potentialMatrice.PHP_EOL;
    var_dump($potentialMatrice(['red' => 0.5, 'green' => 0.5, 'blue' => 1.0]));

} catch (MatriceException $ex) {
    echo 'erreur : '.$ex->getMessage().PHP_EOL;
}
