<?php
class dam_jobmanager_JobFactory extends PinaxObject
{
    public function createJob($name, $params, $description, $type = 'INTERACTIVE')
    {
        $ar = pinax_objectFactory::createModel('dam.jobmanager.models.Job');
        $ar->job_type = $type;
        $ar->job_name = $name;
        $ar->job_params = serialize($params);
        $ar->job_description = $description;
        $ar->job_status = dam_jobmanager_JobStatus::NOT_STARTED;
        $ar->job_progress = 0;
        $ar->job_modificationDate = new pinax_types_DateTime();
        $ar->save();
    }
}