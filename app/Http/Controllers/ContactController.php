<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportContactRequest;
use App\Http\Requests\StoreContactRequest;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactController extends Controller
{
    public function index(): View
    {
        $categories = Category::orderBy('id')->get();
        $tags = Tag::orderBy('id')->get();

        return view('contact.index', compact('categories', 'tags'));
    }

    public function confirm(StoreContactRequest $request): View
    {
        $validated = $request->validated();
        $request->session()->flashInput(
            array_merge(
                $validated,
                $request->only(['tel1', 'tel2', 'tel3'])
            )
        );
        $category = Category::findOrFail($validated['category_id']);
        $tags = Tag::whereIn('id', $validated['tag_ids'] ?? [])
            ->orderBy('id')
            ->get();

        return view('contact.confirm', compact('validated', 'category', 'tags'));
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $tagIds = $validated['tag_ids'] ?? [];
        unset($validated['tag_ids']);
        DB::transaction(function () use ($validated, $tagIds) {
            $contact = Contact::create($validated);
            $contact->tags()->attach($tagIds);
        });
        $request->session()->forget('_old_input');

        return redirect()->route('contacts.thanks');
    }

    public function thanks(): View
    {
        return view('contact.thanks');
    }

    public function export(ExportContactRequest $request): StreamedResponse
    {
        $query = Contact::with('category')
            ->filter($request->validated())
            ->latest()
            ->orderByDesc('id');

        $filename = 'contacts_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // 日本語をExcelで認識しやすくするため、UTF-8 BOMを付ける
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'ID',
                '氏名',
                '性別',
                'メール',
                '電話',
                '住所',
                '建物',
                'カテゴリ',
                '内容',
                '作成日時',
            ], ',', '"', '');

            $genderLabels = [1 => '男性', 2 => '女性', 3 => 'その他'];

            foreach ($query->lazy(200) as $contact) {
                $row = [
                    $contact->id,
                    $contact->first_name.' '.$contact->last_name,
                    $genderLabels[$contact->gender] ?? '',
                    $contact->email,
                    $contact->tel,
                    $contact->address,
                    $contact->building ?? '',
                    $contact->category->content ?? '',
                    $contact->detail,
                    $contact->created_at?->format('Y-m-d H:i:s') ?? '',
                ];

                // 入力文字列が表計算ソフトで数式として扱われるのを防ぐ
                $row = array_map(function ($value) {
                    $value = (string) $value;

                    if (preg_match('/^[\s]*[=+\-@]|^[\t\r\n]/u', $value)) {
                        return "'".$value;
                    }

                    return $value;
                }, $row);

                fputcsv($handle, $row, ',', '"', '');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
