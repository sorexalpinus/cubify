SELECT
    :dimensions,
    :measures
FROM (:baseQuery)
GROUP BY :groupingSequence :withRollup;