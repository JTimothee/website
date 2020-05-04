<?php

namespace Modules\User\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\KeyValue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\User\Http\Requests\Frontend\UpdateInformationRequest;
use Modules\User\Http\Requests\Frontend\UpdateProfileRequest;
use Modules\User\Repositories\Frontend\UserRepository;

class ProfileController extends Controller
{
    /**
     * @var UserRepository
     */
    protected UserRepository $userRepository;

    /**
     * ProfileController new instance.
     *
     * @param  UserRepository $userRepository
     */
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Affichage du profil d'un utilisateur.
     *
     * @param  string  $username
     * @return \Inertia\Response
     */
    public function index(string $username)
    {
        $user = $this->userRepository->with(['activities', 'keyValues', 'replies'])->getByColumn($username, 'username');

        if (!$user) {
            abort('404', "Le profile de cet utilisateur n'est plus disponible ou a été supprimé.");
        }

        $bestReplies = 0;

        foreach ($user->replies as $reply) {
            if ($reply->isBest()) {
                $bestReplies += 1;
            }
        }

        return Inertia::render('user/Profile', [
            'user' => $user,
            'bestReplies' => $bestReplies
        ]);
    }

    /**
     * @param  UpdateInformationRequest $request
     * @return mixed
     */
    public function update(UpdateInformationRequest $request)
    {
        $user = $this->userRepository->updateById(
            $request->user()->id,
            $request->only('first_name', 'last_name'),
        );

        foreach ($request->except(['first_name', 'last_name']) as $key => $value) {
            if(!is_null($user->GetKeyValue($key))){
                KeyValue::where('keyvalue_id', '=', $user->id)
                    ->where('keyvalue_type', '=', $this->userRepository->model())
                    ->where('key', '=', $key)
                    ->update(['value' => $value ?? '']);
            } else {
                KeyValue::create([
                    'key'   => $key,
                    'value' => $value ?? '',
                    'keyvalue_id'   => $user->id,
                    'keyvalue_type' => $this->userRepository->model(),
                ]);
            }
        }

        return back()
            ->with('type', 'data')
            ->with('success', 'Vos informations ont été mise à jour avec succès.');
    }

    /**
     * Mise a jour de la section profil de l'utilisateur.
     *
     * @param  UpdateProfileRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function profile(UpdateProfileRequest $request)
    {
        $result = $this->userRepository->updateProfile(
            $request->user()->id,
            $request->only('username')
        );

        if ($result) {
            $user = $this->userRepository->getById(auth()->id());

            if(!is_null($user->GetKeyValue('biography'))){
                KeyValue::where('keyvalue_id', '=', $user->id)
                    ->where('keyvalue_type', '=', $this->userRepository->model())
                    ->where('key', '=', 'biography')
                    ->update(['value' => $request->input('biography') ?? '']);
            } else {
                KeyValue::create([
                    'keyvalue_id' => $user->id,
                    'keyvalue_type' => $this->userRepository->model(),
                    'key' => 'biography',
                    'value' => $request->input('biography') ?? ''
                ]);
            }

            return back()
                ->with('type', 'profile')
                ->with('success', 'Vos informations ont été mise à jour avec succès.');
        }

        return back()
            ->with('type', 'profile')
            ->with('error', 'Impossible de mettre à jour votre profil.');
    }

    /**
     * Mise a jour de l'avatar d'un utilisateur.
     *
     * @param  Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateAvatar(Request $request)
    {
        $request->validate(['avatar_location' => ['required', 'image']]);

        $this->userRepository->updateAvatar(
            $request->user()->id,
            $request->only('avatar_location')
        );

        return back()
            ->with('type', 'profile')
            ->with('success', 'Votre avatar a été mise à jour avec succès.');
    }
}
