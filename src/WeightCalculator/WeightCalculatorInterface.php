<?php

namespace Anko\Lab1\WeightCalculator;

interface WeightCalculatorInterface
{
    public function calculateWeights(int $userId, array $weights): array;
}
