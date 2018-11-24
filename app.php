<?php


class Matrice {

    private $data = [];

    private $count;

    private $type;

    private $strict;

    public function setColumnDef(int $count, string $type, bool $strict = true) {
        $this->count = $count;
        $this->type = $type;
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

        foreach ($line as $value) {
            $this->addLineValue($lineName, $value);
        }
    }

    /**
     * @param $lineName
     * @param $value
     * @throws Exception
     */
    private function addLineValue($lineName, $value)
    {
        $composedType = explode('::', $this->type);

        if (gettype($value) !== $composedType[0]) {
            if (!$this->strict) {
                return;
            }

            throw new \Exception(sprintf(
                'this line not match type expected (expected: %d, actual: %d)',
                $composedType[0],
                gettype($value)
            ));
        }

        if (!empty($composedType[1]) && get_class($value) !== $composedType[1]) {
            return;
        }

        $this->data[$lineName][] = $value;
    }

    public function toArray() {
        return $this->data;
    }
}


$matrice = new Matrice();
$matrice->setColumnDef(3, 'integer', false);
$matrice->addLine(['a', 'b', 'c']);
$matrice->addLine([5, 10, 15]);
$matrice->addLine([5, 10, 15, 16]);
$matrice->addLine([5, 'd', 15]);

var_dump($matrice->toArray());