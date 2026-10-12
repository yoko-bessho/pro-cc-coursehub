# Course関連テスト 網羅マトリクス

更新日: 2026-10-13

## 対象ファイル

Course エンティティ自体（CRUD・一覧表示・アクセス制御・進捗率計算）を直接テストしているファイルを対象とする。

- `tests/Feature/CourseTest.php` — コースの一覧表示・詳細表示・作成・更新・削除
- `tests/Feature/CoursePolicyTest.php` — コース閲覧のアクセス制御（Policy）
- `tests/Feature/CourseProgressRateTest.php` — コース単位の受講生別進捗率計算

Course に関連するが、別機能（受講登録・レビュー・小テストなど）が主対象のテストは対象外。
`MyCoursesTest`, `EnrollmentTest`, `ReviewTest`, `QuizRetakeTest`, `QuizSubmitTest`, `AdminStudentTest`, `CoachStudentsTest` が該当する。

権限（誰が何をできるか）は下記2枚の**権限マトリクス**にまとめ、スラッグ生成やタグ同期のような権限に関係ない業務ロジックのテストは「機能別テスト（権限以外）」に分けている。
詳細表示だけはコースの `status`（公開・下書き・アーカイブ）によって可否が変わる3次元の権限のため、他の操作と同じ表にはせず別表にしている。

## 権限マトリクス①：一覧表示・作成・更新・削除

| ロール | 一覧表示<br>`GET /courses` | 作成<br>`POST /coach/courses` | 更新<br>`PUT /coach/courses/{id}` | 削除<br>`DELETE /coach/courses/{id}` | 管理者一覧<br>`GET /admin/courses` | 管理者削除<br>`DELETE /admin/courses/{id}` |
|---|---|---|---|---|---|---|
| 受講生 | 許可 ✅ `test_student_can_view_course_list` | 禁止 ✅ `test_student_cannot_create_course`※1 | 禁止 ❌ 未テスト | 禁止 ❌ 未テスト | 禁止 ❌ 未テスト | 禁止 ❌ 未テスト |
| コーチ（自分のコース） | 許可 ❌ 未テスト | 許可 ✅ `test_coach_can_create_course` 他多数 | 許可 ✅ `test_coach_can_update_course` 他 | 許可 ✅ `test_coach_can_delete_own_course` | 禁止 ✅ `test_non_admin_cannot_view_admin_course_list`（AdminCourseTest） | 禁止 ✅ `test_non_admin_cannot_delete_course_via_admin_route`（AdminCourseTest） |
| コーチ（他コーチのコース） | 許可 ❌ 未テスト | （対象コースなしのため適用外） | 禁止 ✅ `test_coach_cannot_update_other_coachs_course` | 禁止 ✅ `test_coach_cannot_delete_other_coaches_course` | 同上（ownership非依存のため同一テストでカバー） | 同上（ownership非依存のため同一テストでカバー） |
| 管理者 | 許可 ❌ 未テスト | 禁止 ✅ `test_admin_cannot_create_course`※2 | 禁止 ✅ `test_admin_cannot_update_course`※2 | 禁止 ✅ `test_admin_cannot_delete_course_via_coach_route`※2 | 許可 ✅ `test_admin_can_view_courses_of_any_status`（AdminCourseTest） | 許可 ❌ 未テスト（※3、仕様未確定のためテスト保留中） |

※1 `test_student_cannot_create_course` は `GET /coach/courses/create` への403を検証している（`POST /coach/courses` 自体への直接テストではない）。ただし `role:coach` ミドルウェアが `/coach` 配下全体を遮断するため、作成アクションも実質カバーしていると見なせる。

※2 管理者がコース作成・更新・削除（`/coach/*`経由）を一切行えないことを確認したテスト。`AdminCourseController` にも作成・更新の手段がないため、管理者はコース内容を編集する手段を持たない。これが意図した仕様か（閲覧・削除のみの権限と割り切る設計か）は要確認として `tests/Feature/CourseTest.php` にコメントで残している。

※3 `App\Policies\CoursePolicy::delete()` はコース作成者本人のみ許可する実装（`$user->id === $course->user_id`）だが、`AdminCourseController::destroy()` は `$this->authorize()` を呼ばずに `$course->delete()` を直接実行しており、Policyの権限定義と矛盾している。「管理者は他コーチのコースも削除できる」が意図した仕様か、Policy側に管理者許可を足し忘れているだけかが未確定のため、正常系テスト（`tests/Feature/AdminCourseTest.php` にコメントアウトで記述済み）は仕様確定まで保留している。

根拠: `routes/web.php` の `role:coach` / `role:admin` ミドルウェア、`App\Policies\CoursePolicy::create/update/delete`、`App\Http\Middleware\RoleMiddleware`（ロール不一致は無条件403、管理者の特別扱いなし）。

## 権限マトリクス②：詳細表示（`GET /courses/{course}`）× コースstatus

| ロール \ コースstatus | 公開（published） | 下書き（draft） | アーカイブ（archived） |
|---|---|---|---|
| 受講生 | 許可 ✅ `test_student_can_view_published_course`（CourseTest / CoursePolicyTest 両方） | 禁止 ✅ `test_student_cannot_view_draft_course` | 禁止 ✅ `test_student_cannot_view_archived_course` |
| コーチ（自分のコース） | 許可 ✅ `test_course_author_can_view_own_published_course` | 許可 ✅ `test_course_author_can_view_own_draft_course` | 許可 ✅ `test_course_author_can_view_own_archived_course` |
| コーチ（他コーチのコース） | 許可 ✅ `test_coach_can_view_other_coachs_published_course` | 禁止 ✅ `test_coach_cannot_view_other_coachs_draft_course` | 禁止 ✅ `test_coach_cannot_view_other_coachs_archived_course` |
| 管理者 | 許可 ❌ 未テスト | 許可 ✅ `test_admin_can_view_draft_course` | 許可 ❌ 未テスト |

根拠: `App\Policies\CoursePolicy::view`（`admin` または `course.user_id === user.id` なら常に許可、それ以外は `status === 'published'` のときのみ許可）。

## 機能別テスト（権限以外の業務ロジック）

権限マトリクスでカバーされない、画面表示や入力値の業務ロジックに関するテスト。

### 一覧表示の表示内容

| 区分 | 検証内容 | テストメソッド |
|---|---|---|
| 正常系 | カテゴリ・タイトル・説明・コーチ名・チャプター数・受講者数が表示される | `test_course_list_displays_category_title_description_coach_chapter_count_and_enrollment_count` |
| 正常系 | チャプター・受講者が0件のとき「0件」と表示される | `test_course_list_displays_zero_counts_when_course_has_no_chapters_or_enrollments` |
| 正常系 | カテゴリで絞り込める | `test_course_list_can_be_filtered_by_category` |
| 正常系 | キーワードで検索できる（タイトル一致） | `test_course_list_can_be_searched` |

### コース作成の業務ロジック

| 区分 | 検証内容 | テストメソッド |
|---|---|---|
| 正常系 | タイトルからslugが生成される | `test_course_slug_is_generated_from_title` |
| 正常系 | slug化できないタイトル（日本語のみ等）は `course-` + 数字のタイムスタンプ形式のslugになる | `test_course_slug_falls_back_to_timestamp_for_japanese_title` |
| 正常系 | slugが重複する場合は連番を付与して一意にする | `test_course_slug_is_made_unique_on_collision` |
| 正常系 | 画像付きでコースを作成できる | `test_coach_can_create_course_with_image` |
| 正常系 | status=publishedのとき published_at が設定される | `test_published_at_is_set_when_status_is_published` |
| 正常系 | 既存タグを紐付けられる | `test_coach_can_sync_existing_tags_when_creating_course` |
| 正常系 | 新規タグを作成して紐付けられる | `test_coach_can_create_new_tags_when_creating_course` |
| 正常系 | 作成時に初期チャプター（「はじめに」）が自動生成される | `test_initial_chapter_is_created_when_creating_course` |
| 異常系 | 同一コーチが同じタイトルのコースを作成できない | `test_coach_cannot_create_course_with_duplicate_title` |
| 異常系 | 新規タグが255文字を超える場合は作成できない | `test_coach_cannot_create_course_with_new_tags_longer_than_255_characters` |

### コース更新の業務ロジック

| 区分 | 検証内容 | テストメソッド |
|---|---|---|
| 正常系 | 既存タグの同期と新規タグの作成を同時に行える | `test_coach_can_sync_tags_and_create_new_tags_when_updating_course` |
| 正常系 | 自分の現タイトルを維持したまま更新できる（unique制約の自己除外） | `test_coach_can_update_course_while_keeping_its_own_title` |
| 異常系 | 自分の他コースと同じタイトルには更新できない | `test_coach_cannot_update_course_with_title_already_used_by_another_own_course` |
| 異常系 | 新規タグが255文字を超える場合は更新できない | `test_coach_cannot_update_course_with_new_tags_longer_than_255_characters` |

### 進捗率計算（`Course::getProgressRatesForUsers()`）

| 区分 | 検証内容 | テストメソッド |
|---|---|---|
| 正常系 | 非公開レッスンを除き、公開レッスンを全て完了していれば100% | `test_progress_rate_is_100_percent_when_all_published_lessons_are_completed_even_with_unpublished_lessons` |
| 正常系 | 公開レッスンの一部完了時に完了割合が反映される | `test_progress_rate_reflects_partial_completion_of_published_lessons` |
| 正常系 | レッスンが0件のコースは0% | `test_progress_rate_is_0_percent_when_course_has_no_lessons` |

## 要確認事項（仕様の疑義）

テストを実装する過程で見つかった、実装とPolicy定義の不整合。仕様が確定するまでテストを保留している箇所。

- **管理者はコース内容を編集する手段を持たない**: `CoursePolicy::create()` は `role === 'coach'` のみ許可、`AdminCourseController` にも作成・更新の手段がない。管理者の役割を「閲覧・削除のみ」と割り切る設計として正しいか要確認（`CourseTest.php` の `test_admin_cannot_create_course` / `test_admin_cannot_update_course` 周辺にコメントあり）。
- **管理者のコース削除権限がPolicyと矛盾**: `CoursePolicy::delete()` はコース作成者本人のみ許可する実装だが、`AdminCourseController::destroy()` は `authorize()` を呼ばずに誰のコースでも削除できる別ルートを提供している。意図した仕様か、Policy側の実装漏れかが未確定のため、`AdminCourseTest.php` に正常系テストをコメントアウトで保留中（※3参照）。

## 未カバー・ギャップ

権限マトリクス・業務ロジック表の「❌ 未テスト」のうち、特に優先度が高いと考えられるもの。

- **一覧表示 (`/courses`) へのコーチ・管理者のアクセス**: 受講生のみテスト済み。ロール制限がない画面だが、回帰防止の観点では薄い。
- **Form Request の個別バリデーション**: `StoreCourseRequest` / `UpdateCourseRequest` のうち、`title` の重複チェックと `new_tags` の255文字超のみがテスト対象。必須項目欠落（title/category_id/description/difficulty/status）、`category_id` が存在しない値、`difficulty`/`status` の許容値外、画像の形式・サイズ超過（`StoreCourseRequest`）は未テスト。
- **一覧表示の絞り込み**: `difficulty` パラメータによる絞り込みは実装済みだが未テスト。下書き・アーカイブ済みコースが一覧に表示されないことを直接検証するテストもない（`status=published` 固定のため間接的には保証されている）。
- **検索の対象範囲**: `search` はタイトル・説明の両方に一致するが、説明文側のマッチは未テスト。
