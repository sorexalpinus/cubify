SELECT *
FROM (SELECT :maskHash,
             :dimensions,
             :measures
      FROM (
               :subQuery
               ) base1
      GROUP BY :groupingSequence) base2
WHERE `Mask` IN (:masks);
