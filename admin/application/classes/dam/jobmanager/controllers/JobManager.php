<?php
class dam_jobmanager_controllers_JobManager extends pinax_mvc_core_Command
{
    public function execute()
    {
        // esegue il primo job non ancora eseguito
        $it = pinax_objectFactory::createModelIterator('dam.jobmanager.models.Job');
        $it->where('job_status', 'NOT_STARTED');

        if ($it->count() > 0) {
            $ar = $it->first();
            $jobService = pinax_objectFactory::createObject($ar->job_name, $ar->job_id);
            $jobService->run();
        }
    }

}