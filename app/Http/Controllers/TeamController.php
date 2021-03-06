<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Team;
use Auth;
use Log;
use User;
use Invite;
use Transaction;
use Category;
use Illuminate\Support\Facades\Mail;
use App\Mail\TeamInvite;
use App\Mail\JoinedTeam;



class TeamController extends Controller
{

public function __construct()
{
    parent::__construct();  
	$this->middleware('auth');
}
	
 public function create()
 {
 	if(!Auth::check()) {
        return redirect('/login')
            ->with('error', 'You must be logged in!');
    }
 }

 public function changeName(Team $team, Request $request)
 {
 	if(!Auth::check()) {
        return redirect('/login')
            ->with('error', 'You must be logged in!');
        }
    if(!Auth::user()->team->owner) {
            return redirect('/login')
                ->with('error', 'You are not authorized to change that!');
            }

    $this->validate($request, [
		'teamName' => 'required|max:255'
    	]);

    $team->name = $request->teamName;
    $team->save();

    return redirect(route('settings.team'))
    ->with('success', 'Your team name has been changed.');
 }

 public function sendInvite()
 {
 	return view('teams.sendInvite');
 }

 public function sent(Request $request)
 {
 	if(!Auth::check()) {
        return redirect('/login')
        ->with('error', 'You must be logged in!');
    }

 	$this->validate($request, [
			'email' => 'required|email',
			]);

 	$team = Auth::user()->team;
 	$existingUser = User::where('email', $request->email)->first();
 	$existingInvite = Invite::where('email', $request->email)->where('team_id', $team->id)->first();
 	if ($existingUser) {
 		if(!$existingUser->isOnTeam($team->id))
 		{
            $invite = New Invite;
            $invite->user_id = Auth::user()->id;
            $invite->team_id = $team->id;
            $invite->email = $request->email;
            $invite->token = str_random(12);
            $invite->save();
            Mail::to($invite->email)->send(new TeamInvite($invite));
            return redirect()->back()->with('success', 'Your invite has been sent.');
 		}
        return redirect()->back()->with('info', "That user is already on your team.");
 	}

 	if ($existingInvite) {
        Mail::to($existingInvite->email)->send(new TeamInvite($existingInvite));
 		return redirect()->back()->with('info', "You have already invited that user, we resent the invitation.");
 	}

 	$invite = New Invite;
 	$invite->user_id = Auth::user()->id;
 	$invite->team_id = $team->id;
 	$invite->email = $request->email;
 	$invite->token = str_random(12);
 	$invite->save();

 	Mail::to($invite->email)->send(new TeamInvite($invite));

 	return redirect(route('settings.team'))
 		->with('success', 'Your invite has been sent!');
 }

 public function acceptInvite($token)
{
	if(!Auth::check()) {
        return redirect('/login')
            ->with('error', 'To accept this invite, you must login or sign up.');
            }

	$invite = Invite::where('token', $token)->first();

    if($invite->email != Auth::user()->email)
    {
        return redirect(route('home'))
            ->with('info', 'The invitation to join this team does not match your credentials.');
    }

	if(!$invite)
	{
		return redirect(route('home'))
			->with('error', 'Sorry, that link is no longer valid.');
	}

	$team = $invite->team;

	if($team->users->contains(Auth::user()->id))
	{
        return redirect('/')
            ->with('warning', 'You are already on that team.');
    }
        
    $team->users()->attach(Auth::user()->id);
	Mail::to($invite->team->owner->email)->send(new JoinedTeam($invite));
	Log::info('User '.$invite->email.' has accepted an invitation to team '.$team->id);	
	$invite->delete();
	return redirect(route('settings.team'))
		->with('success', 'You have joined that team!');
}

public function remove(User $user)
{
	if(!Auth::check()) {
        return redirect('/login')
            ->with('error', 'You must be logged in!');
    } 
            
    $team = Auth::user()->team;
    $team->users()->detach($user->id);
    if(!$user->isOnTeam($team->id)){
        return redirect()->back()
            ->with('info', 'That user was not on your team.');
    }
	return redirect(route('settings.team'))
		->with('success', 'You have removed that user.');
}

public function leave($id)
{
	if(!Auth::check()) {
        return redirect('/login')
            ->with('error', 'You must be logged in!');
    }
	$team = Team::find($id);
	$user = Auth::user();
    if(!$user->isOnTeam($team->id)){
	    return redirect()->back()
        ->with('error', 'You cannot leave a team you are not on.'); 
       }
    $team->users()->detach($user->id);
        return redirect(route('settings.team'))
            ->with('success', 'You have left the team.');
}

public function deleteInvite($id)
{
	$invite = Invite::find($id);
	if($invite->user_id != Auth::user()->id){
		return redirect(route('dashboard', Auth::user()->team))
    		->with('error', 'You cannot edit that invitation.');
	}
	$invite->delete();
	return redirect(route('settings.team'))
		->with('success', 'You have cancelled the invitation.');
} 

public function denyInvite(Invite $invite)
{
    if(Auth::user()->email = $invite->email){
        $invite->delete();
        return redirect()->back()
            ->with('info', 'That invitation has been denied.');
    }
}

public function default($id)
{
    $user = Auth::user();
    if(! $user->isOnTeam($id))
    {
        return redirect(route('settings.team'))
            ->with('error', 'You are not on that team.');
    }

    $user->default_team_id = $id;
    $user->save();

    return redirect(route('settings.team'))
        ->with('success', 'Your default team has been updated.');

}


}
