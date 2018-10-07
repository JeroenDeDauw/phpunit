<?php
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PHPUnit\Framework\Constraint;

use Countable;
use Iterator;
use IteratorAggregate;
use Traversable;

class Count extends Constraint
{
    /**
     * @var int
     */
    private $expectedCount;

    /**
     * @var null|\SplObjectStorage
     */
    private $traversableCounts;

    public function __construct(int $expected)
    {
        parent::__construct();

        $this->expectedCount = $expected;
    }

    public function toString(): string
    {
        return \sprintf(
            'count matches %d',
            $this->expectedCount
        );
    }

    /**
     * Evaluates the constraint for parameter $other. Returns true if the
     * constraint is met, false otherwise.
     */
    protected function matches($other): bool
    {
        return $this->expectedCount === $this->getCountOf($other);
    }

    /**
     * @param iterable $other
     */
    protected function getCountOf($other): ?int
    {
        if ($other instanceof Countable || \is_array($other)) {
            return \count($other);
        }

        if ($other instanceof Traversable) {
            return $this->getCountOfTraversable($other);
        }
    }

    /**
     * Returns the description of the failure.
     *
     * The beginning of failure messages is "Failed asserting that" in most
     * cases. This method should return the second part of that sentence.
     *
     * @param mixed $other evaluated value or object
     */
    protected function failureDescription($other): string
    {
        return \sprintf(
            'actual size %d matches expected size %d',
            $this->getCountOf($other),
            $this->expectedCount
        );
    }

    private function getCountOfTraversable(Traversable $traversable): int
    {
        if ($this->traversableCounts === null) {
            $this->traversableCounts = new \SplObjectStorage();
        }

        while ($traversable instanceof IteratorAggregate) {
            $traversable = $traversable->getIterator();
        }

        if ($this->traversableCounts->contains($traversable)) {
            return $this->traversableCounts[$traversable];
        }

        if ($traversable instanceof Iterator) {
            return $this->countIteratorWithoutChangingPositionIfPossible($traversable);
        }

        return $this->getCountOfTraversableThatIsNotIteratorOrIteratorAggregate($traversable);
    }

    private function countIteratorWithoutChangingPositionIfPossible(Iterator $iterator): int
    {
        $key          = $iterator->key();
        $isRewindable = $this->attemptIteratorRewind($iterator);
        $count        = $this->getCountOfIterator($iterator);

        if ($isRewindable) {
            $this->moveIteratorToPosition($iterator, $key);
        } else {
            $this->traversableCounts->attach($iterator, $count);
        }

        return $count;
    }

    /**
     * Returns the total number of iterations from a iterator.
     * This will fully exhaust the iterator.
     */
    private function getCountOfIterator(Iterator $iterator): int
    {
        for ($count = 0; $iterator->valid(); $iterator->next()) {
            ++$count;
        }

        return $count;
    }

    private function attemptIteratorRewind(Iterator $iterator): bool
    {
        if ($iterator instanceof \Generator || $iterator instanceof \NoRewindIterator) {
            return false;
        }

        try {
            $iterator->rewind();
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    private function moveIteratorToPosition(Iterator $iterator, $key): void
    {
        $iterator->rewind();

        while ($iterator->valid() && $key !== $iterator->key()) {
            $iterator->next();
        }
    }

    private function getCountOfTraversableThatIsNotIteratorOrIteratorAggregate(Traversable $traversable): int
    {
        $count = \iterator_count($traversable);

        $this->traversableCounts->attach($traversable, $count);

        return $count;
    }
}
