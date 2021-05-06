#|/bin/bash
for I in {0..10000}
  do php ./solrPublish.php $I 100
done
