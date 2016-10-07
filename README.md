# F3Que
A Queuing library for the PHP FatFreeFramework

# Examples of usage
`$que = \Que::instance();
while($job_id = $que->reserve('jobs')) $que->complete($job_id, perform_task( $job_id ) );    `

# Public Methods

`setTrigger( channel, script ) 
checkTriggers( channel )`

You can set command line scripts to run on triggers based que channels when jobs are added to a que. 
This gives the ability to launch external scripts in the background to properly handle the jobs in the que. 

`$que->setTrigger('jobs','/home/scripts/eatQue.php');
$que->add('jobs',['first':'Danjelo','last':'Morgaux']);`

This would call your script to eat on the que. The contents of the que eating script would look much like the first Examples of Usage

`reserve ( channel )` - Pulls a job out of a que rotation and returns its job_id. 

`add ( channel, data )` - Adds a job containing <data> into the que channel <channel>

`unreserve ( job_id )` - Removes the reservation of a reserved job. Good for rolling back jobs

`size ( channel )` - Returns the number of jobs waiting in the channel

`complete ( job_id , clear )` - marks a job as completed, if clear is true then it will remove the job from the que, otherwise it will be left in the que to be redone at a later date.

`all ( channel )` - return all jobs in a channel

`resetChannel ( channel )` - clear que for channel

`resetAll ( )` - clear que for all channels
