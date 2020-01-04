SELECT
    :maskHash,
    :dimensions,
    :measures
FROM (
         :subQuery
         ) base
GROUP BY :groupingSequence