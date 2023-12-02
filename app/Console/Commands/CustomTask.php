<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Models\History;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CustomTask extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'history:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // $this->info('Custom task executed successfully!');
        $histories = History::where('event', '=', 'Reserve')
            ->where('proved', '=', false)
            ->get();
        foreach ($histories as $history) {
            if ($this->calculateDaysDifference($history->created_at) > 7) {
                File::where('id', '=', $history->file_id)->update(['status' => true, 'booker_id' => null]);
                $history->delete();
            }
        }
        $this->info('Custom task executed successfully!');
        return Command::SUCCESS;
    }


    public function calculateDaysDifference($date)
    {
        // Parse the input date using Carbon
        $inputDate = Carbon::parse($date);

        // Get the current date
        $currentDate = Carbon::now();

        // Calculate the difference in days
        $differenceInDays = $currentDate->diffInDays($inputDate);

        return $differenceInDays;
    }
}
